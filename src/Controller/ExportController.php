<?php

namespace Drupal\pageplanner\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\pageplanner\PageplannerManager;
use Drupal\pageplanner\PageplannerStoryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use szeidler\Pageplanner\PageplannerClient;

class ExportController extends ControllerBase {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * The Pageplanner Manager.
   *
   * @var \Drupal\pageplanner\PageplannerManager
   */
  protected $pageplannerManager;

  /**
   * The story json builder.
   *
   * @var \Drupal\pageplanner\PageplannerStoryBuilder
   */
  protected $storyJsonBuilder;

  /**
   * The Pageplanner API Client.
   *
   * @var \szeidler\Pageplanner\PageplannerClient
   */
  protected $client;

  /**
   * Constructs a ExportController.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \szeidler\Pageplanner\PageplannerClient $client
   *   The Pageplanner API Client.
   * @param \Drupal\pageplanner\PageplannerManager $pageplanner_manager
   *   The Pageplanner Manager..
   * @param \Drupal\pageplanner\PageplannerStoryBuilder $story_json_builder
   *   The story json builder.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, PageplannerClient $client, PageplannerManager $pageplanner_manager, PageplannerStoryBuilder $story_json_builder) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->client = $client;
    $this->storyJsonBuilder = $story_json_builder;
    $this->pageplannerManager = $pageplanner_manager;

    $this->store = $this->tempStoreFactory->get('pageplanner_export_form_data');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('pageplanner.client'),
      $container->get('pageplanner.manager'),
      $container->get('pageplanner.story_json_builder')
    );
  }

  /**
   * Shows the choose destination buttons.
   *
   * @param string $entity_type
   *  The entity type.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *  The entity to be exported.
   *
   * @return array
   *   A render array showing the choose destination page.
   */
  public function chooseDestination($entity_type, ContentEntityInterface $entity) {
    $build = [];

    $this->store->set('entity_type', $entity_type);
    $this->store->set('entity_id', $entity->id());


    $build['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $build['issue'] = [
      '#type' => 'button',
      '#name' => 'issue',
      '#value' => $this->t('Place in issue (disabled)'),
      '#disabled' => TRUE,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    $url = Url::fromRoute('pageplanner.pageplanner_export.preview', [
      'export_type' => 'inbox',
    ]);
    $url->setOptions([
      'attributes' => [
        'class' => ['use-ajax', 'button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => '80%']),
      ],
    ]);

    $build['inbox'] = [
      '#type' => 'link',
      '#title' => t('Place in inbox'),
      '#url' => $url,
      '#weight' => 25,
    ];
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $build;
  }

  /**
   * Shows a preview of the to be exported content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony request.
   *
   * @return array
   *   An render array showing the preview.
   */
  public function preview(Request $request) {
    $export_type = $request->get('export_type');
    $this->store->set('export_type', $export_type);

    $build = [];
    $build['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $entity = $this->getEntityFromStorage();
    $data = $this->storyJsonBuilder->build($entity);

    foreach ($this->pageplannerManager->providedFields() as $field_name => $field_label) {
      if (!empty($data[$field_name])) {
        // If the Pageplanner property is an array, fetch the array value.
        $value = $data[$field_name];
        if (is_array($value)) {
          if (isset($value['value'])) {
            $value = $value['value'];
          }
          else {
            $value = implode(', ', $value);
          }
        }
        $build[$field_name] = [
          '#type' => 'item',
          '#title' => $field_label,
          '#markup' => $value,
        ];
      }
    }

    $url = Url::fromRoute('pageplanner.pageplanner_export.process');
    $url->setOptions([
      'attributes' => [
        'class' => ['use-ajax', 'button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => '80%']),
      ],
    ]);

    $build['process'] = [
      '#type' => 'link',
      '#title' => t('Export to Pageplanner'),
      '#url' => $url,
      '#weight' => 25,
    ];
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $build;
  }

  /**
   * Finally export the current content to Pageplanner.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function process() {
    $response = new AjaxResponse();
    $entity = $this->getEntityFromStorage();

    try {
      $api_response = $this->exportStory();
      $response->addCommand(new RedirectCommand(Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])
        ->toString()));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
      $content = $this->chooseDestination($entity->getEntityTypeId(), $entity);
      $response->addCommand(new OpenModalDialogCommand(t('Pageplanner Export: Choose destination'), $content, ['width' => '80%']));
    }

    return $response;
  }

  /**
   * Exports the story to Pageplanner.
   */
  public function exportStory() {
    $entity = $this->getEntityFromStorage();
    $data = $this->storyJsonBuilder->build($entity);
    $response = $this->client->createStory($data);
    $pageplanner_id = (string) $response->toArray();

    $this->messenger()
      ->addStatus($this->t('The article was exported successfully to Pageplanner. Pageplanner story id: @pageplanner_id', ['@pageplanner_id' => $pageplanner_id]));
    $this->deleteStore();

    return $response;
  }

  /**
   * Checks that the stored entity is ready to be exported.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function readyToBeExported(AccountInterface $account) {
    $entity = $this->getEntityFromStorage();
    $export_type = $this->store->get('export_type');

    return AccessResult::allowedIf($entity && !empty($export_type));
  }

  /**
   * Loads the entity from the private temp storage.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function getEntityFromStorage() {
    $entity_type = $this->store->get('entity_type');
    $entity_id = $this->store->get('entity_id');

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)
      ->load($entity_id);

    return $entity;
  }

  /**
   * Helper method that removes all the keys from the store collection.
   */
  protected function deleteStore() {
    $keys = ['entity_type', 'entity_id', 'export_type'];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }

}
