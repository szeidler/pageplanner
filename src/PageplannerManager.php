<?php

namespace Drupal\pageplanner;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class PageplannerManager {

  use StringTranslationTrait;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * PageplannerManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Returns list of Drupal properties that should be skipped for the mapping.
   *
   * @return array
   *   Associative array with property keys.
   */
  public function skippedProperties() {
    return [
      'uuid',
      'vid',
      'type',
      'langcode',
      'default_langcode',
      'uid',
      'revision_timestamp',
      'revision_log',
      'revision_uid',
    ];
  }

  /**
   * Returns the Pageplanner properties.
   *
   * @return array
   *   Associative array with field names as keys and descriptions as values.
   */
  public function providedFields() {
    return [
      'id' => $this->t('ID'),
      'title' => $this->t('Title'),
      'abstract' => $this->t('Abstract'),
      'body' => $this->t('Body'),
      'tags' => $this->t('Tags'),
    ];
  }

  /**
   * Returns the fields as an key/value option list.
   *
   * @param $entity_type
   * @param $type_id
   *
   * @return array
   */
  public function getFieldOptionList($entity_type, $type_id) {
    $options = ['_none' => $this->t('- Skip field -')];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type, $type_id) as $field_name => $field) {
      if (!in_array($field_name, $this->skippedProperties())) {
        $options[$field_name] = $field->getLabel();
      }
    }

    return $options;
  }

}
