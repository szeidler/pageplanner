<?php

namespace Drupal\pageplanner;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Build the story for the Pageplanner Export.
 */
class PageplannerStoryBuilder implements PageplannerStoryBuilderInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs the PageplannerStoryJsonBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build(ContentEntityInterface $entity) {
    $field_map = $this->getFieldMap($entity);

    $data = [];

    foreach ($field_map as $destination_field => $source_field) {
      if ($entity->hasField($source_field) && ($value = $this->getValue($entity, $source_field, $destination_field))) {
        $data[$destination_field] = $value;
      }
    }

    // Makes it possible to alter the data.
    \Drupal::moduleHandler()->alter('pageplanner_post_data', $data, $entity);

    return $data;
  }

  /**
   * Returns the Pageplanner field map stored in the bundle.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity from which's bundle the field map is retrieved from.
   *
   * @return array
   *   The field map as key/value pairs.
   */
  public function getFieldMap(ContentEntityInterface $entity) {
    $bundle_type_id = $entity->getEntityType()->getBundleEntityType();
    $bundle_type = $this->entityTypeManager->getStorage($bundle_type_id)
      ->load($entity->bundle());
    $field_map = $bundle_type->getThirdPartySetting('pageplanner', 'field_map', []);

    return $field_map;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ContentEntityInterface $entity, $source_field, $destination_field) {
    $field_definition = $entity->get($source_field)->getFieldDefinition();
    $value = $this->getSourceFieldValue($entity->get($source_field));

    switch ($destination_field) {
      case 'title':
      case 'body':
      case 'abstract':
        $isHtml = $this->isFormattedField($field_definition);

        if ($isHtml) {
          $value = $this->stripHtmlTags($value);
        }

        $value = ['value' => $value, 'isHtml' => $isHtml];
        break;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldValue(FieldItemListInterface $source_field) {
    $value = NULL;

    // Get the field type to get the appropriate field values.
    $field_type = $source_field->getFieldDefinition()->getType();
    switch ($field_type) {
      case 'list_integer':
      case 'list_float':
      case 'list_string':
        $list_items = $source_field->getValue();
        $value = array_map(function ($item) {
          return $item['value'];
        }, $list_items);
        break;

      case 'entity_reference':
        /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $source_field */
        $entities = $source_field->referencedEntities();
        $value = array_map(function ($entity) {
          return $entity->label();
        }, $entities);
        break;

      default:
        if (!empty($source_field->value)) {
          $value = $source_field->value;
        }
    }

    return $value;
  }

  /**
   * Checks whether the field is a formatted field, that might have HTML.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return bool
   *   True if it is a formatted field.
   */
  public function isFormattedField(FieldDefinitionInterface $fieldDefinition) {
    return in_array($fieldDefinition->getType(), [
      'text',
      'text_long',
      'text_with_summary',
    ]);
  }

  /**
   * Strip the HTML except whitelisted tags from the Pageplanner API.
   *
   * @param string $value
   *   The value of the textfield.
   *
   * @return string
   *   The cleaned value.
   */
  protected function stripHtmlTags($value) {
    $value = strip_tags($value, '<p><h1><h2><h3><h4><h5><h6>');
    return $value;
  }

}
