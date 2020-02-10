<?php

namespace Drupal\osk_content_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OSK Content import settings form.
 */
class OskContentImportSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Path\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return string
   *   Form name
   */
  public function getFormId() {
    return 'osk_content_import_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @var array $form The Form object
   * @var Drupal\Core\Form\FormStateInterface $form_state The state of the form
   *
   * @return array
   *   Form array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['cloud-storage'] = [
      '#title' => $this->t('Cloud Storage'),
      '#type' => 'details',
      '#open' => TRUE,
    ];

    $form['cloud-storage']['cloud_available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cloud option available'),
      '#default_value' => $this->config('osk_content_import.settings')->get('cloud_available'),
      '#description' => $this->t("If you want to import or export files from a cloud, this has to be set."),
    ];

    $form['cloud-storage']['cloud_cache_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cloud file cache dir'),
      '#default_value' => $this->config('osk_content_import.settings')->get('cloud_cache_dir'),
      '#description' => $this->t("If you want files to store in a local cache, relative path here."),
    ];

    $form['cloud-storage']['cloud_blob_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Blog Storage to use'),
      '#default_value' => $this->config('osk_content_import.settings')->get('cloud_blob_type'),
      '#description' => $this->t("Choose which blob storage to use"),
      '#options' => [
        'digital_ocean' => $this->t('Digital Ocean Space'),
      ],
    ];

    $form['cloud-storage']['do'] = [
      '#title' => $this->t('Digital Ocean'),
      '#type' => 'details',
    ];

    $form['cloud-storage']['do']['digitalocean_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Space Url'),
      '#default_value' => $this->config('osk_content_import.settings')->get('digitalocean_url'),
      '#description' => $this->t("Something like https://***.***.digitaloceanspaces.com"),
    ];

    $form['cloud-storage']['do']['digitalocean_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project Prefix'),
      '#description' => $this->t('A unique project name using alphabetical lowercase characters'),
      '#default_value' => $this->config('osk_content_import.settings')->get('digitalocean_prefix'),
    ];

    $form['cloud-storage']['do']['digitalocean_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Key'),
      '#default_value' => $this->config('osk_content_import.settings')->get('digitalocean_key'),
    ];

    $form['cloud-storage']['do']['digitalocean_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Secret'),
      '#default_value' => $this->config('osk_content_import.settings')->get('digitalocean_secret'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['osk_content_import.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    $this->config('osk_content_import.settings')
      ->setData($values)
      ->save();

    $new_dir = 'public://' . $values['cloud_cache_dir'];
    if (!file_exists($new_dir)) {
      mkdir($new_dir, 0777);
    }

    drupal_set_message($this->t('Changes saved.'));
  }

}
