<?php

namespace Drupal\tide_demo_content\Plugin\ConfigFilter;

use Drupal\config_ignore\Plugin\ConfigFilter\IgnoreFilter;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Ignore all demo config.
 *
 * @ConfigFilter(
 *   id = "tide_demo_content_config_ignore",
 *   label = "Tide Demo Content Config Ignore",
 *   weight = 100
 * )
 */
class TideDemoContentIgnoreFilter extends IgnoreFilter implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StorageInterface $active) {
    $configuration['ignored'] = [
      '*tide_demo_content*',
      '*tide-demo-content*',
      '*tide_demo*',
      '*tide-demo*',
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition, $active);
  }

  /**
   * {@inheritdoc}
   */
  public function filterWrite($name, array $data) : ?array {
    if ($name === 'core.extension') {
      $excluded_modules = ['tide_demo_content' => 'tide_demo_content'];
      $data['module'] = array_diff_key($data['module'], $excluded_modules);
    }
    elseif ($this->matchConfigName($name)) {
      return NULL;
    }
    return $data;
  }

}
