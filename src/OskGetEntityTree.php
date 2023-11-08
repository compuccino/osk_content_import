<?php

namespace Drupal\osk_content_import;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class OskGetEntityTree.
 */
class OskGetEntityTree {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a OskGetEntityTree object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, ConfigFactoryInterface $config_factory) {
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Get one entities all dependencies.
   *
   * @param string $entity_type
   *   The entity type name.
   * @param string $id
   *   The entities id.
   * @param array $previous
   *   Previously used entities so we don't get circular dependencies.
   *
   * @return array
   *   A rendered pseudo entity array.
   */
  public function getArrayRepresentation($entity_type, $id, array $previous = []) {
    // If we hit a circular dependency or it's a excluded field, stop.
    if (isset($previous[$entity_type][$id]) || (count($previous) && in_array($entity_type, $this->alwaysExcludedFields()))) {
      return NULL;
    }

    $output = [];

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
    // If it's a config we do not save it.
    if (!method_exists($entity, 'baseFieldDefinitions')) {
      return NULL;
    }

    $bundle_type = $entity->bundle();

    // Store so we don't get circular dependencies.
    $previous[$entity_type][$id] = TRUE;

    // Make array of dependencies.
    $dependencies = [];

    // Go through and look for references recursively.
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_type);
    foreach ($bundle_fields as $bundle_field) {
      // If it's a entity reference we need to make it available.
      $settings = $bundle_field->getSettings();

      if (isset($settings['target_type'])) {
        // Get the field data.
        $field_data_values = $entity->get($bundle_field->getName())->getValue();
        foreach ($field_data_values as $field_data_value) {
          // Go one step down.
          if (isset($field_data_value['target_id'])) {
            $dependency = $this->getArrayRepresentation($settings['target_type'], $field_data_value['target_id'], $previous);
            if (is_array($dependency)) {
              $dependencies[] = $dependency;
            }
          }
        }
      }
    }

    // Let other modules alter the entity tree.
    $moduleHandler = \Drupal::moduleHandler();
    foreach ($moduleHandler->getModuleList() as $module) {
      if ($moduleHandler->hasImplementations('oci_extend_entity_tree')) {
        $function = $module . '_oci_extend_entity_tree';
        $function($dependencies, $previous, $entity, $entity_type, $id, $bundle_type);
      }
    }

    $output[] = [
      'id' => $id,
      'type' => $entity_type,
      'bundle' => $bundle_type,
      'name' => osk_content_import_entity_naming($entity, $entity_type),
      'dependencies' => $dependencies,
    ];

    return $output;
  }

  /**
   * Always excluded fields.
   *
   * @return array
   *   Array of fields that should manually be excluded.
   */
  public function alwaysExcludedFields() {
    return [];
  }

}
