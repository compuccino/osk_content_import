<?php

namespace Drupal\osk_content_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;

/**
 * Build Osk Content Import form.
 */
class OskContentEntityImport extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'osk_content_export_entity_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $id = NULL) {
    $form['upload'] = [
      '#title' => $this->t('Upload Yaml or Tarball'),
      '#description' => $this->t('If you want to import from a file on your hard drive, upload it using this field.'),
      '#type' => 'details',
      '#open' => TRUE,
    ];

    $form['upload']['content_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Content File (yml or tgz)'),
      '#description' => t('If you want to import from a file, upload it here.'),
      '#upload_validators' => [
        'file_validate_extensions' => ['yml tgz'],
      ],
    ];

    $form['server'] = [
      '#title' => $this->t('Server path'),
      '#description' => $this->t('If you instead want to import using the file system, use a absolute path or a path relative to Drupal.'),
      '#type' => 'details',
    ];

    $form['server']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path on server'),
      '#description' => $this->t("If you want to import using the file system, use a absolute path or a path relative to Drupal."),
    ];

    $form['extras'] = [
      '#title' => $this->t('Extra options'),
      '#type' => 'details',
    ];

    $form['extras']['url_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL Path of content'),
      '#description' => $this->t("If you want the content to be imported to some special url. Start with leading /"),
    ];

    $form['extras']['uid'] = [
      '#type' => 'select',
      '#options' => $this->getUserOptions(),
      '#title' => $this->t('Author'),
      '#description' => $this->t("If you want the content to be owned by a specific author add it here."),
    ];

    $form['extras']['creation_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set creation date to now'),
      '#description' => $this->t("If you want to set creation date to now, click this."),
      '#default_value' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import content'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    set_time_limit(120);
    $values = $form_state->cleanValues()->getValues();
    $form_file = $form_state->getValue('content_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      $importer = \Drupal::service('osk_content_import.osk_content_import');
      $name = $importer->import($file->getFileUri(), $values['url_path'], $values['uid'], $values['creation_date']);
      drupal_set_message($this->t('The content %name was created', ['%name' => $name]));
    }
  }

  /**
   * Helper function to provide the users for the form.
   */
  private function getUserOptions() {
    $ids = \Drupal::entityQuery('user')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->execute();
    $users = User::loadMultiple($ids);
    $options = [0 => t('Anonymous')];
    foreach ($users as $user) {
      $options[$user->id()] = $user->label();
    }
    return $options;
  }

}
