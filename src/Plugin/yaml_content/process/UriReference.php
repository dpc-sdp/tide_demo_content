<?php

namespace Drupal\tide_demo_content\Plugin\yaml_content\process;

use Drupal\yaml_content\Plugin\ProcessingContext;
use Drupal\yaml_content\Plugin\yaml_content\process\Reference;

/**
 * Plugin for querying and loading URI of a referenced entity.
 *
 * @YamlContentProcess(
 *   id = "uri_reference",
 *   title = @Translation("Entity URI Reference Processor"),
 *   description = @Translation("Attach the URI of an entity reference.")
 * )
 */
class UriReference extends Reference {

  /**
   * {@inheritdoc}
   */
  public function process(ProcessingContext $context, array &$field_data) {
    [$entity_type, $filter_params] = $this->configuration;

    $entity_storage = $this->entityTypeManager->getStorage($entity_type);

    // Use query factory to create a query object for the node of entity_type.
    $query = $entity_storage->getQuery('AND');

    // Apply filter parameters.
    foreach ($filter_params as $property => $value) {
      $query->condition($property, $value);
    }

    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      $entity = $entity_storage->create($filter_params);
      $entity_ids = [$entity->id()];
    }

    if (!empty($entity_ids)) {
      // Use the first match for our value.
      $entity_id = array_shift($entity_ids);
      $entity = $entity_storage->load($entity_id);
      if ($entity && $entity->hasLinkTemplate('canonical')) {
        // A Uri string represents the data in the Url object, followed by
        // `route:entity.node.canonical;node={entity_id}` format, which does not
        // fit to an entity field. The uri column in database must be
        // `entity:{entity_type_id}/{id}` or an uri with a schema.
        $field_data['uri'] = 'entity:' . $entity_type . '/' . $entity_id;
      }
      else {
        $field_data['uri'] = 'internal://' . $entity->getEntityTypeId() . '/' . $entity_id;
      }

      // Remove process data to avoid issues when setting the value.
      unset($field_data['#process']);

      return $field_data['uri'];
    }
    $this->throwParamError('Unable to find referenced content', $entity_type, $filter_params);
    return NULL;
  }

}
