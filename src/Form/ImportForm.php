<?php

namespace Drupal\tide_demo_content\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Utility\Random;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\yaml_content\ContentLoader\ContentLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImportForm.
 *
 * @package Drupal\tide_demo_content\Form
 */
class ImportForm extends FormBase {

  /**
   * File System.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fs;

  /**
   * YAML Content loader.
   *
   * @var \Drupal\yaml_content\ContentLoader\ContentLoaderInterface
   */
  protected $contentLoader;

  /**
   * ImportForm constructor.
   *
   * @param \Drupal\Core\File\FileSystem $fs
   *   File system.
   * @param \Drupal\yaml_content\ContentLoader\ContentLoaderInterface $content_loader
   *   Content Loader.
   */
  public function __construct(FileSystem $fs, ContentLoaderInterface $content_loader) {
    $this->fs = $fs;
    $this->contentLoader = $content_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('yaml_content.content_loader')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tide_demo_content_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['import'] = [
      '#title' => $this->t('Paste your demo content YAML here'),
      '#type' => 'textarea',
      '#rows' => 24,
      '#required' => TRUE,
      '#description' => $this->t('For more information on how to construct the YAML, please refer to the YAML Content module: @link', [
        '@link' => Link::fromTextAndUrl('https://www.drupal.org/project/yaml_content', Url::fromUri('https://www.drupal.org/project/yaml_content', ['attributes' => ['target' => 'blank']]))->toString(),
      ]),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      // Decode the submitted import.
      Yaml::decode($form_state->getValue('import'));
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('import', $this->t('The import failed with the following message: %message', ['%message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = $form_state->getValue('import');
    $form_state->setRebuild(TRUE);

    $temp_dir = 'temporary://tide_demo_content';
    $demo_temp_dir = $temp_dir . '/content';
    if (file_prepare_directory($demo_temp_dir, FILE_CREATE_DIRECTORY + FILE_MODIFY_PERMISSIONS)) {
      $randomiser = new Random();
      $temp_name = 'demo_content_' . $randomiser->name(16) . '.content.yml';
      $temp_file = $demo_temp_dir . DIRECTORY_SEPARATOR . $temp_name;

      if (file_put_contents($temp_file, $data) !== FALSE) {
        try {
          $this->contentLoader->setContentPath($temp_dir);
          $entities = $this->contentLoader->loadContent($temp_name);
          $this->messenger()->addMessage($this->formatPlural(count($entities), '1 demo entity has been imported.', '@count demo entities have been imported.') . ' Only node and media type entities are listed below - ');
          if (!empty($entities)) {
            foreach ($entities as $entity) {
              if ($entity->getEntityTypeId() == 'node' || $entity->getEntityTypeId() == 'media') {
                $this->messenger()->addMessage(t('@entity_type/@entity_id', [
                  '@entity_type' => $entity->getEntityTypeId(),
                  '@entity_id' => $entity->id(),
                ]));
              }
            }
          }
          $form_state->setRebuild(FALSE);
        }
        catch (\Exception $exception) {
          $this->messenger()->addError($exception->getMessage());
          watchdog_exception('tide_demo_content', $exception);
        }
        finally {
          @unlink($temp_file);
          $this->fs->rmdir($demo_temp_dir);
        }
      }
      else {
        $this->messenger()->addError($this->t('The specified file @file could not be created.', ['@file' => $temp_file]));
      }
    }
    else {
      $this->messenger()->addError($this->t('The specified directory @directory could not be created.', ['@directory' => $demo_temp_dir]));
    }
  }

}
