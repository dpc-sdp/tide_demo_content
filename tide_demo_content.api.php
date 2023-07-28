<?php

/**
 * @file
 * Hooks provided by Tide Demo Content module.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\yaml_content\ContentLoader\ContentLoaderInterface;

/**
 * Reacts when a demo entity is imported.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The imported entity.
 * @param array $content_data
 *   The YAML data loaded from .content.yml file.
 * @param \Drupal\yaml_content\ContentLoader\ContentLoaderInterface $content_loader
 *   The YAML Content Loader service.
 */
function hook_tide_demo_content_entity_imported(EntityInterface $entity, array $content_data, ContentLoaderInterface $content_loader = NULL) {
  \Drupal::messenger()->addMessage(t('The demo @entity_type %label has been imported', [
    '@entity_type' => $entity->getEntityTypeId(),
    '%label' => $entity->label(),
  ]));
}

/**
 * Excludes unwanted demo content collections.
 *
 * When a collection is ignored, all its dependants will be also ignored due to
 * missing collections. Hence, be aware when ignoring the essential collections
 * such as: 'tide_demo_content:tide_core.demo',
 * 'tide_demo_content:tide_media.demo', and 'tide_demo_content:tide_site.demo'.
 *
 * @param array $collections
 *   All collections which were discovered by tide_demo_content.
 *
 * @return string[]
 *   The list of collection names to be excluded.
 */
function hook_tide_demo_content_collection_ignore(array $collections) : array {
  $ignored_collections = ['my_module:my_unwanted_collection.demo'];
  if (isset($collections['tide_alert:tide_alert.demo'])) {
    $ignored_collections[] = 'tide_alert:tide_alert.demo';
  }
  return $ignored_collections;
}
