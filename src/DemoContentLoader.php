<?php

namespace Drupal\tide_demo_content;

use Drupal\Component\Graph\Graph;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\yaml_content\ContentLoader\ContentLoaderInterface;

/**
 * Class DemoContentLoader for tide_demo_content.
 *
 * @package Drupal\tide_demo_content
 */
class DemoContentLoader {
  use StringTranslationTrait;

  const DIRECTORY = 'demo_content';

  /**
   * The Demo Content Repository.
   *
   * @var \Drupal\tide_demo_content\DemoContentRepository
   */
  protected $repository;

  /**
   * The Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The YAML Content Loader.
   *
   * @var \Drupal\yaml_content\ContentLoader\ContentLoaderInterface
   */
  protected $contentLoader;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The demo content collections.
   *
   * @var array
   */
  protected $collections;

  /**
   * The directed acyclic graph.
   *
   * @var array
   */
  protected $collectionGraph;

  /**
   * DemoContentLoader constructor.
   *
   * @param \Drupal\tide_demo_content\DemoContentRepository $repository
   *   The demo content repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\yaml_content\ContentLoader\ContentLoaderInterface $content_loader
   *   The content loader.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(DemoContentRepository $repository, ModuleHandlerInterface $module_handler, ContentLoaderInterface $content_loader, MessengerInterface $messenger, TranslationInterface $translation) {
    $this->repository = $repository;
    $this->moduleHandler = $module_handler;
    $this->contentLoader = $content_loader;
    $this->messenger = $messenger;
    $this->stringTranslation = $translation;
  }

  /**
   * Find all demo content collections defined in .tide_demo_content.yml files.
   */
  protected function findAllDemoContentCollection() {
    if (isset($this->collections)) {
      return;
    }

    $this->collections = [];
    $discovery = new YamlDiscovery('tide_demo_content', $this->moduleHandler->getModuleDirectories());
    $definitions = $discovery->findAll();
    foreach ($definitions as $module_name => $collections) {
      foreach ($collections as $name => $collection) {
        $collection_name = $module_name . ':' . $name;
        $collection['module'] = $module_name;
        $collection += [
          'weight' => 0,
          'dependencies' => [
            'modules' => [],
            'collections' => [],
          ],
          'content' => [],
        ];
        $collection['dependencies']['modules'] = $collection['dependencies']['modules'] ?: [];
        $collection['dependencies']['collections'] = $collection['dependencies']['collections'] ?: [];

        // Only use items satisfying theirs module dependencies.
        if ($this->checkModulesEnabled($collection['dependencies']['modules'])) {
          $this->collections[$collection_name] = $collection;
        }
        else {
          $this->messenger->addMessage($this->t('Ignored %name as it does not meet its module dependencies.', ['%name' => $collection_name]));
        }
      }
    }

    // Filter out all items not meeting theirs collection dependencies.
    foreach ($this->collections as $collection_name => &$collection) {
      $missing_dependencies = $this->checkMissingCollections($collection['dependencies']['collections']);
      if (!empty($missing_dependencies)) {
        unset($this->collections[$collection_name]);
        $this->messenger->addMessage($this->t('Ignored %name as its required collection %collection is missing.', [
          '%name' => $collection_name,
          '%collection' => reset($missing_dependencies),
        ]));
      }
    }
  }

  /**
   * Gets the dependency graph of all collections.
   *
   * @return array
   *   The dependency graph of all collections.
   */
  public function getCollectionGraph() {
    if (!isset($this->collectionGraph)) {
      $graph = [];
      $this->findAllDemoContentCollection();
      foreach ($this->collections as $collection_name => $collection) {
        $graph_key = $collection_name;
        if (!isset($graph[$graph_key])) {
          $graph[$graph_key] = [
            'edges' => [],
            'name' => $graph_key,
          ];
        }

        $graph[$graph_key]['collection_weight'] = $collection['weight'];

        // Include all dependencies in the graph so that topographical sorting
        // works.
        foreach ($collection['dependencies']['collections'] as $dependency) {
          $graph[$dependency]['edges'][$graph_key] = TRUE;
          $graph[$dependency]['name'] = $dependency;
        }
      }

      if (!empty($graph)) {
        // Ensure that order of the graph is consistent.
        uasort($graph, function ($a, $b) {
          return $b['collection_weight'] <=> $a['collection_weight'];
        });
        $graph_object = new Graph($graph);
        $graph = $graph_object->searchAndSort();

        // Sort the graph by dependency weighting, collection weighting, name.
        $sorts = $this->prepareMultisort($graph, [
          'weight',
          'collection_weight',
          'name',
        ]);
        array_multisort($sorts['weight'], SORT_ASC, SORT_NUMERIC,
          $sorts['collection_weight'], SORT_ASC, SORT_NUMERIC,
          $sorts['name'], SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE,
          $graph);
      }
      $this->collectionGraph = $graph;
    }
    return $this->collectionGraph;
  }

  /**
   * Extracts data from the graph for use in array_multisort().
   *
   * @param array $graph
   *   The graph to extract data from.
   * @param array $keys
   *   The keys whose values to extract.
   *
   * @return array
   *   An array keyed by the $keys passed in. The values are arrays keyed by the
   *   row from the graph and the value is the corresponding value for the key
   *   from the graph.
   */
  protected function prepareMultisort(array $graph, array $keys) {
    $return = array_fill_keys($keys, []);
    foreach ($graph as $graph_key => $graph_row) {
      foreach ($keys as $key) {
        $return[$key][$graph_key] = $graph_row[$key];
      }
    }
    return $return;
  }

  /**
   * Load all demo content.
   */
  public function loadAllDemoContent() {
    foreach ($this->getCollectionGraph() as $collection_name => $graph_item) {
      $collection = $this->collections[$collection_name];
      foreach ($collection['content'] as $content_item) {
        try {
          $demo_dir = $this->moduleHandler->getModule($collection['module'])->getPath() . DIRECTORY_SEPARATOR . static::DIRECTORY;
          $yaml_content_dir = $demo_dir . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR;
          $demo_item = $yaml_content_dir . $content_item;
          if (is_dir($demo_item)) {
            $files = $this->discoverFiles($demo_item);
            if (!empty($files)) {
              $this->contentLoader->setContentPath($demo_dir);
              foreach ($files as $file) {
                $filename = str_replace($yaml_content_dir, '', $file->uri);
                $this->contentLoader->loadContent($filename);
              }
            }
          }
          else {
            $this->contentLoader->setContentPath($demo_dir);
            $this->contentLoader->loadContent($content_item);
          }
        }
        catch (\Exception $exception) {
          $this->messenger->addError($exception->getMessage());
          watchdog_exception('tide_demo_content', $exception);
          return;
        }
      }
    }
  }

  /**
   * Scan and discover content files for import.
   *
   * The scanner assumes all content files will follow the naming convention of
   * '*.content.yml'.
   *
   * @param string $path
   *   The directory path to be scanned for content files.
   * @param string $mask
   *   (Optional) A file name mask to limit matches in scanned files.
   *
   * @return array
   *   An associative array of objects keyed by filename with the following
   *   properties as returned by FileSystemInterface::scanDirectory():
   *
   *   - 'uri'
   *   - 'filename'
   *   - 'name'
   *
   * @see \Drupal\Core\File\FileSystemInterface::scanDirectory()
   * @see \Drupal\yaml_content\Service\LoadHelper::discoverFiles()
   */
  protected function discoverFiles($path, $mask = '/.*\.content\.yml/') {
    // Identify files for import.
    $files = \Drupal::service('file_system')->scanDirectory($path, $mask, [
      'key' => 'filename',
      'recurse' => TRUE,
    ]);

    // Sort the files to ensure consistent sequence during imports.
    ksort($files);

    return $files;
  }

  /**
   * Check if all required modules are enabled.
   *
   * @param array $required_modules
   *   List of module name.
   *
   * @return bool
   *   FALSE if at least one module is not enabled.
   */
  protected function checkModulesEnabled(array $required_modules) {
    foreach ($required_modules as $module_name) {
      if (!$this->moduleHandler->moduleExists($module_name)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check if there is any missing collection.
   *
   * @param string[] $required_collections
   *   List of required collection name.
   *
   * @return array
   *   List of missing collection name.
   */
  protected function checkMissingCollections(array $required_collections) {
    return array_diff($required_collections, array_keys($this->collections));
  }

}
