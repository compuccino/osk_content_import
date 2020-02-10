<?php

namespace Drupal\osk_content_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Build Osk Content Export form.
 */
class OskContentEntityExport extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'osk_content_export_entity_export';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $id = NULL) {
    // Load the whole representation as an array.
    $tree = $this->getArrayRepresentationFlat($entity_type, $id);

    $form['main'] = [
      '#type' => 'details',
      '#title' => $this->t('Main Info'),
      '#open' => TRUE,
    ];

    $form['main']['filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#description' => $this->t('If you want to have your own filename, enter it here without an extension'),
    ];

    $form['main']['entity-' . $tree[0]['id'] . '-' . $tree[0]['type']] = [
      '#type' => 'checkbox',
      '#title' => '<strong>' . $tree[0]['name'] . '</strong> (type: ' . $tree[0]['type'] . ', bundle: ' . $tree[0]['bundle'] . ', id: ' . $tree[0]['id'] . ')',
      '#default_value' => TRUE,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
      '#value' => TRUE,
    ];

    $form['main']['entity-' . $tree[0]['id'] . '-' . $tree[0]['type'] . '-level'] = [
      '#type' => 'value',
      '#title' => $tree[0]['level'],
    ];

    if (count($tree) > 1) {
      $form['dependencies'] = [
        '#type' => 'details',
        '#title' => $this->t('Dependecies'),
      ];
    }

    foreach ($tree as $key => $form_item) {
      if ($key) {
        $form['dependencies']['entity-' . $form_item['id'] . '-' . $form_item['type']] = [
          '#type' => 'checkbox',
          '#title' => $form_item['name'],
          '#default_value' => TRUE,
          '#value' => TRUE,
        ];

        $form['dependencies']['entity-' . $form_item['id'] . '-' . $form_item['type'] . '-level'] = [
          '#type' => 'value',
          '#value' => $form_item['level'],
        ];
      }
    }

    $form['export_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Export options'),
      '#open' => TRUE,
    ];

    $form['export_options']['export_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export author'),
      '#description' => $this->t('Export user that authors the content. (Not recommended)'),
      '#default_value' => FALSE,
    ];

    $form['export_options']['obfuscate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Obfuscate fields'),
      '#description' => $this->t('A comma separated list of fields that should be obfuscated in the form {entity_type}.{bundle_type}.{field_name}, e.g. user.user.pass'),
      '#default_value' => FALSE,
    ];

    $form['export_options']['resources'] = [
      '#type' => 'select',
      '#title' => $this->t('Resources/Files'),
      '#description' => $this->t('How will the content deliver the resources (files like mp4, jpg)'),
      '#options' => [
        'package' => t('All in one zip package'),
        'cloud' => t('Upload to cloud'),
      ],
      '#required' => TRUE,
      '#default_value' => 'package',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export content'),
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
    $exporter = \Drupal::service('osk_content_import.osk_content_export');
    $entities = [];
    $i = 0;
    foreach ($values as $name => $value) {
      if (substr($name, 0, 7) == 'entity-') {
        // Every other one is a level definition.
        if (!isset($level) || !$level && $value) {
          $parts = explode('-', substr($name, 7));
          $id = array_shift($parts);
          $entities[$i] = [
            'entity_type' => implode('-', $parts),
            'entity_id' => $id,
          ];
          $level = TRUE;
        }
        else {
          $entities[$i]['level'] = $value ? $value : 0;
          $i++;
          $level = FALSE;
        }
      }
    }
    $cloud = $values['resources'] == 'package' ? FALSE : TRUE;
    $exporter->export($entities, $cloud, $values['export_user'], explode(' ', $values['obfuscate']), $values['filename']);
    exit;
  }

  /**
   * Get one entities flat.
   *
   * @param string $entity_type
   *   The entity type name.
   * @param string $id
   *   The entities id.
   *
   * @return array
   *   An entity tree array.
   */
  public function getArrayRepresentationFlat($entity_type, $id) {
    $entities = \Drupal::service('osk_content_import.osk_get_entity_tree')->getArrayRepresentation($entity_type, $id);
    return $this->entityArrayItterator($entities);
  }

  /**
   * Recursive function to make the entity structure flat.
   *
   * @param array $entities
   *   The entity array.
   * @param int $level
   *   The level of the tree.
   *
   * @return array
   *   An cleanup entity array.
   */
  public function entityArrayItterator(array $entities, $level = 0) {
    $out[] = [
      'id' => $entities[0]['id'],
      'type' => $entities[0]['type'],
      'bundle' => $entities[0]['bundle'],
      'name' => $entities[0]['name'],
      'level' => $level,
    ];
    $level++;
    foreach ($entities[0]['dependencies'] as $dependency) {
      $itterated = $this->entityArrayItterator($dependency, $level);
      $out = array_merge($out, $itterated);
    }
    return $out;
  }

}
