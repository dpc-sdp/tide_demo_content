<?php

namespace Drupal\tide_demo_content\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\tide_demo_content\DemoContentLoader;
use Drupal\tide_demo_content\DemoContentRepository;
use Drupal\yaml_content\Event\ContentParsedEvent;
use Drupal\yaml_content\Event\EntityPostSaveEvent;
use Drupal\yaml_content\Event\YamlContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to track entities imported by tide_demo_content.
 *
 * @package Drupal\tide_demo_content\EventSubscriber
 */
class YamlContent implements EventSubscriberInterface {

  /**
   * The demo content repository.
   *
   * @var \Drupal\tide_demo_content\DemoContentRepository
   */
  protected $repository;

  /**
   * The module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * YamlContent constructor.
   *
   * @param \Drupal\tide_demo_content\DemoContentRepository $repository
   *   The demo content repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Extension\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(DemoContentRepository $repository, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager) {
    $this->repository = $repository;
    $this->moduleHandler = $module_handler;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[YamlContentEvents::ENTITY_POST_SAVE][] = ['trackEntity'];
    $events[YamlContentEvents::CONTENT_PARSED][] = ['modifyParsedContent'];
    return $events;
  }

  /**
   * Track the saved demo entity.
   *
   * @param \Drupal\yaml_content\Event\EntityPostSaveEvent $event
   *   The EntityPostSaveEvent.
   */
  public function trackEntity(EntityPostSaveEvent $event) {
    $loader = $event->getContentLoader();
    $path = trim($loader->getContentPath(), '/');

    // Only track entities imported from tide_demo_content.
    if (substr($path, -strlen(DemoContentLoader::DIRECTORY)) === DemoContentLoader::DIRECTORY) {
      $this->repository->trackEntity($event->getEntity());
      $this->moduleHandler->invokeAll('tide_demo_content_entity_imported', [
        $event->getEntity(),
        $event->getContentData(),
        $event->getContentLoader(),
      ]);
    }
  }

  /**
   * Remove unsupported fields.
   */
  public function modifyParsedContent(ContentParsedEvent $event) {
    $content = $event->getParsedContent();
    if (!empty($content)) {
      foreach ($content as $delta => $details) {
        if (isset($details['entity']) && isset($details['type'])) {
          try {
            $fields_info = $this->entityFieldManager->getFieldDefinitions($details['entity'], $details['type']);
            $fields = array_keys($fields_info);
          }
          catch (\Exception $exception) {
            throw new UnsupportedEntityTypeDefinitionException();
          }
          foreach ($details as $key => $item) {
            if ($key === 'entity' || $key === 'type') {
              continue;
            }
            if (!in_array($key, $fields)) {
              unset($content[$delta][$key]);
            }
          }
        }
      }
    }
    $event->setParsedContent($content);
    return $event;
  }

}
