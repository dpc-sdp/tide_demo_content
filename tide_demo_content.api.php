<?php

/**
 * @file
 * Hooks provided by Tide Demo Content module.
 */

use Drupal\yaml_content\ContentLoader\ContentLoaderInterface;
use Drupal\Core\Entity\EntityInterface;

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
