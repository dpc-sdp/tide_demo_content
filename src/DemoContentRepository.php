<?php

namespace Drupal\tide_demo_content;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\media\MediaInterface;

/**
 * The Repository to manage demo content.
 *
 * @package Drupal\tide_demo_content
 */
class DemoContentRepository {

  /**
   * The tracking table.
   */
  const TABLE_NAME = 'tide_demo_content_tracking';

  /**
   * Whether the tracking table exists.
   *
   * @var bool
   */
  protected $trackingTableExists = FALSE;

  /**
   * Default Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * DemoContentRepository constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The default DB connection.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(Connection $connection, EntityTypeManager $entityTypeManager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
    $this->trackingTableExists = $this->connection->schema()->tableExists(static::TABLE_NAME);
  }

  /**
   * Track the entity permanently in the demo table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\tide_demo_content\DemoContentRepository
   *   The current repository instance.
   */
  public function trackEntity(EntityInterface $entity) : DemoContentRepository {
    try {
      $data = [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'entity_id' => $entity->id(),
      ];
      $this->connection->merge(static::TABLE_NAME)
        ->keys($data)
        ->updateFields($data)
        ->execute();
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_demo_content', $exception);
    }

    return $this;
  }

  /**
   * Add a demo entity to the repository.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $entity_type_id
   *   Override entity type with a custom value.
   * @param string $bundle
   *   Override bundle with a custom value.
   * @param bool $tracking
   *   Whether to track the entities.
   *
   * @return \Drupal\tide_demo_content\DemoContentRepository
   *   The current repository instance.
   */
  public function addDemoEntity(EntityInterface $entity, string $entity_type_id = NULL, string $bundle = NULL, bool $tracking = TRUE) : DemoContentRepository {
    $entity_type_id = $entity_type_id ?: $entity->getEntityTypeId();
    $bundle = $bundle ?: $entity->bundle();

    $this->entities[$entity_type_id][$bundle][$entity->id()] = $entity;
    if ($tracking) {
      $this->trackEntity($entity);
    }

    return $this;
  }

  /**
   * Add multiple demo entities to the repository.
   *
   * @param array $entities
   *   The array of entities.
   * @param bool $tracking
   *   Whether to track the entities.
   *
   * @return \Drupal\tide_demo_content\DemoContentRepository
   *   The current repository instance.
   */
  public function addDemoEntities(array $entities, bool $tracking = TRUE) : DemoContentRepository {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      $this->addDemoEntity($entity, NULL, NULL, $tracking);
    }

    return $this;
  }

  /**
   * Retrieve demo entities created in the same session.
   *
   * @param string|null $entity_type_id
   *   Entity type ID, eg. node or taxonomy_term.
   * @param string|null $bundle
   *   Bundle, eg. sites, page, lading_page.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|\Drupal\Core\Entity\EntityInterface
   *   The list of entities.
   */
  public function getDemoEntities(string $entity_type_id = NULL, string $bundle = NULL) {
    if ($entity_type_id) {
      if (isset($this->entities[$entity_type_id])) {
        if ($bundle) {
          if (isset($this->entities[$entity_type_id][$bundle])) {
            return $this->entities[$entity_type_id][$bundle];
          }
          return [];
        }
        return $this->entities[$entity_type_id];
      }
      return [];
    }

    return $this->entities;
  }

  /**
   * Remove all tracked entities.
   *
   * @param bool $untrack
   *   Whether to remove the reference of the entity from the tracking table.
   *
   * @return \Drupal\tide_demo_content\DemoContentRepository
   *   The current repository instance.
   */
  public function removeTrackedEntities(bool $untrack = FALSE) : DemoContentRepository {
    try {
      if (!$this->trackingTableExists) {
        return $this;
      }

      $query = $this->connection->select(static::TABLE_NAME, 'demo')
        ->fields('demo')
        ->execute();

      $results = $query->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($results as $result) {
        try {
          $fids_to_delete = [];
          $entity = $this->entityTypeManager->getStorage($result['entity_type'])
            ->load($result['entity_id']);
          if ($entity) {
            if ($untrack) {
              $this->untrackEntity($entity);
            }

            // Collect the files from media entities to delete later.
            if ($entity instanceof MediaInterface) {
              $field_name = ($entity->bundle() === 'image') ? 'field_media_image' : 'field_media_file';
              if ($entity->hasField($field_name)) {
                $fid = $entity->{$field_name}->target_id;
                $fids_to_delete[$fid] = $fid;
              }
            }

            $entity->delete();
          }

          // Delete the files from removed media.
          foreach ($fids_to_delete as $fid) {
            $file = $this->entityTypeManager->getStorage('file')->load($fid);
            if ($file) {
              $file->delete();
            }
          }
        }
        catch (\Exception $exception) {
          watchdog_exception('tide_demo_content', $exception);
        }
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_demo_content', $exception);
    }

    return $this;
  }

  /**
   * Remove the reference of the entity from the tracking table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\tide_demo_content\DemoContentRepository
   *   The current repository instance.
   */
  public function untrackEntity(EntityInterface $entity) : DemoContentRepository {
    try {
      if (!$this->trackingTableExists) {
        return $this;
      }

      $this->connection->delete(static::TABLE_NAME)
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('bundle', $entity->bundle())
        ->condition('entity_id', $entity->id())
        ->execute();
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_demo_content', $exception);
    }

    return $this;
  }

}
