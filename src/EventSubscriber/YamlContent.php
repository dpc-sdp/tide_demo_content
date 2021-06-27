<?php

namespace Drupal\tide_demo_content\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\tide_demo_content\DemoContentLoader;
use Drupal\tide_demo_content\DemoContentRepository;
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
   * YamlContent constructor.
   *
   * @param \Drupal\tide_demo_content\DemoContentRepository $repository
   *   The demo content repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(DemoContentRepository $repository, ModuleHandlerInterface $module_handler) {
    $this->repository = $repository;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[YamlContentEvents::ENTITY_POST_SAVE][] = ['trackEntity'];

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

}
