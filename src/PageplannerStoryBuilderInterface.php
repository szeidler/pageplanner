<?php

namespace Drupal\pageplanner;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines an interface for the PageplannerStoryBuilder.
 */
interface PageplannerStoryBuilderInterface {

  /**
   * Builds the data array that should be send to the Pageplanner API.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to export.
   *
   * @return array
   *   The data array.
   */
  public function build(ContentEntityInterface $entity);

  /**
   * Get the field value and prepare it for the Pageplanner data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to export.
   * @param string $source_field
   *   The source field in Drupal.
   * @param string $destination_field
   *   The destination property at Pageplanner.
   *
   * @return array|string|null
   *   The value for being exported.
   */
  public function getValue(ContentEntityInterface $entity, $source_field, $destination_field);

  /**
   * Get the field value for the current field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $source_field
   *   The source field in Drupal.
   *
   * @return array|string|null
   *   The Drupal source for being exported.
   */
  public function getSourceFieldValue(FieldItemListInterface $source_field);

}
