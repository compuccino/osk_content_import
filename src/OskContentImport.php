<?php

namespace Drupal\osk_content_import;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\osk_content_import\Blobstorage\DigitalOcean;
use Drupal\pathauto\PathautoState;

/**
 * Class OskContentImport.
 */
class OskContentImport {

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
   * The keymap for ids.
   *
   * @var array
   */
  protected $keyMap;

  /**
   * The working dir for import.
   *
   * @var string
   */
  protected $workingDir;

  /**
   * The author id.
   *
   * @var int
   */
  protected $author;

  /**
   * The uri path.
   *
   * @var string
   */
  protected $uri;

  /**
   * Boolean wether or not to remove timestamps.
   *
   * @var bool
   */
  protected $removeTimestamps;

  /**
   * A FileSystem instance.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The path of the base directory for asset files to import.
   *
   * @var string
   */
  protected $filesBaseDir;

  /**
   * Constructs a OskContentImport object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   A file system instance.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, ConfigFactoryInterface $config_factory, FileSystemInterface $fileSystem) {
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Set the base directory path for asset files to import.
   *
   * @param string $path
   *   The path to set.
   */
  public function setAssetFilesBaseDir(string $path) {
    $this->filesBaseDir = $path;
  }

  /**
   * Import one or many yml files.
   *
   * @param string $path
   *   An array with entity type, entity id and level in tree.
   * @param string $uri
   *   The path that the new content should be located at.
   * @param string $uid
   *   The author of the new content.
   * @param bool $remove_timestamp
   *   Should the date be saved as now.
   *
   * @return mixed
   *   The entity of the newly created entity.
   */
  public function import($path, $uri = '', $uid = NULL, $remove_timestamp = TRUE) {
    $entities = $this->importWithNestedOutput($path, $uri, $uid, $remove_timestamp);
    return end($entities)['entity'];
  }

  /**
   * Import one or many yml files.
   *
   * @param string $path
   *   An array with entity type, entity id and level in tree.
   * @param string $uri
   *   The path that the new content should be located at.
   * @param string $uid
   *   The author of the new content.
   * @param bool $remove_timestamp
   *   Should the date be saved as now.
   *
   * @return array
   *   A nested output of all entities created
   */
  public function importWithNestedOutput($path, $uri = '', $uid = NULL, $remove_timestamp = TRUE) {
    $this->author = $uid;
    $this->uri = $uri;
    $this->removeTimestamps = $remove_timestamp;

    if (pathinfo($path, PATHINFO_EXTENSION) == 'yml') {
      $all_entities = Yaml::parse(file_get_contents($path));
    }
    elseif (pathinfo($path, PATHINFO_EXTENSION) == 'tgz') {
      $file = $this->untarFile($path);
      if (!$file) {
        return FALSE;
      }
      $all_entities = Yaml::parse(file_get_contents($file));
    }
    else {
      return FALSE;
    }
    // Create an levelized array so we insert the entities in the right order.
    $levelize = [];
    foreach ($all_entities as $entity_array) {
      $levelize[$entity_array['level']][] = $entity_array;
    }
    krsort($levelize);
    $entities = [];
    foreach ($levelize as $level => $entities_array) {
      foreach ($entities_array as $entity_array) {
        $entity = $this->importEntity($entity_array, $level);
        if (is_object($entity)) {
          $entities[] = [
            'entity' => $entity,
            'type' => $entity_array['entity_type'],
          ];
        }
      }
    }
    return $entities;
  }

  /**
   * Import one entity.
   *
   * @param array $entity_array
   *   An entity array to import.
   * @param int $level
   *   The level in the hierarchy of the entity being imported.
   *
   * @return mixed
   *   The entity of the newly created entity.
   */
  protected function importEntity(array $entity_array, $level) {
    // Look for special cases.
    switch ($entity_array['entity_type']) {
      case 'file':
        $entity = $this->importFile($entity_array, $level);
        break;

      case 'user':

        break;

      case 'paragraph':
        $entity = $this->importParagraph($entity_array, $level);
        break;

      default:
        // Let other modules import manually.
        $found = FALSE;
        foreach (\Drupal::moduleHandler()->getImplementations('oci_import_entity_' . $entity_array['entity_type']) as $module) {
          $function = $module . 'oci_import_entity_' . $entity_array['entity_type'];
          $function($entity_array, $level);
          $found = TRUE;
        }
        if (!$found) {
          $entity = $this->importDefault($entity_array, $level);
        }
        break;
    }
    return isset($entity) ? $entity : '';
  }

  /**
   * Import one file.
   *
   * @param array $file_array
   *   An file array to import.
   * @param int $level
   *   The level in the hierarchy of the entity being imported.
   *
   * @return \Drupal\Core\Entity
   *   A full entity object.
   */
  protected function importFile(array $file_array, $level) {
    // Check where the file is located.
    $file_path = $file_array['representation']['uri'][0];
    $uuid = $file_array['representation']['uuid'][0]['value'] ?? NULL;
    if (substr($file_path, 0, 15) == 'digitalocean://') {
      $config = $this->configFactory->get('osk_content_import.settings');
      // Download the file from digitalocean.
      $storage = new DigitalOcean();
      $data = $storage->download($file_path, $config);
    }
    else {
      $data['path'] = 'public://' . $file_path;
      $data['file'] = $this->filesBaseDir . '/' . $file_path;
    }

    // Create all directories if they do not exist.
    $path = dirname($data['path']);
    if (!$this->fileSystem->prepareDirectory($path)) {
      $this->fileSystem->mkdir($path, 0777, TRUE);
    }

    $uri = $this->fileSystem->saveData(file_get_contents($data['file']), $data['path']);
    $file = File::create([
      'uri' => $uri,
    ]);
    if ($uuid) {
      $file->set('uuid', $uuid);
    }

    $file->save();
    $this->keyMap[$file_array['entity_id']] = $file->id();

    return $file;
  }

  /**
   * Paragraph importer.
   *
   * @param array $entity_array
   *   An file array to import.
   * @param int $level
   *   The level in the hierarchy of the entity being imported.
   *
   * @return \Drupal\Core\Entity
   *   A full entity object.
   */
  protected function importParagraph(array $entity_array, $level) {
    $entity_controller = \Drupal::entityTypeManager()->getStorage($entity_array['entity_type']);
    // Make sure to replace any entity field.
    $entity_prepared = [];
    foreach ($entity_array['representation'] as $field_name => $field_values) {
      foreach ($field_values as $field_key => $field_value) {
        if (substr($field_name, 0, 6) != 'field_') {
          $entity_prepared[$field_name] = $field_value[key($field_value)];
        }
        elseif (isset($field_value['target_id']) && isset($this->keyMap[$field_value['target_id']])) {
          if (is_array($this->keyMap[$field_value['target_id']])) {
            // First set other fields.
            $entity_prepared[$field_name][$field_key] = $field_value;
            // Then reset keys.
            $entity_prepared[$field_name][$field_key]['target_id'] = $this->keyMap[$field_value['target_id']]['id'];
            $entity_prepared[$field_name][$field_key]['target_revision_id'] = $this->keyMap[$field_value['target_id']]['revision_id'];
          }
          else {
            // First set other fields.
            $entity_prepared[$field_name][$field_key] = $field_value;
            // Then reset keys.
            $entity_prepared[$field_name][$field_key]['target_id'] = $this->keyMap[$field_value['target_id']];
          }
        }
        else {
          $entity_prepared[$field_name][$field_key] = $field_value;
        }
      }
    }
    unset($entity_prepared['id']);
    unset($entity_prepared['parent_id']);
    $entity_prepared['uid'] = !is_null($this->author) ? $this->author : \Drupal::currentUser()->id();
    if ($this->removeTimestamps && isset($entity_prepared['created'])) {
      unset($entity_prepared['created']);
    }
    $entity = $entity_controller->create($entity_prepared);
    $entity->save();
    // If we store revisions we set it here.
    $rid = $entity->getRevisionId();
    $this->keyMap[$entity_array['entity_id']] = ['id' => $entity->id(), 'revision_id' => $rid];
    return $entity;
  }

  /**
   * Default importer of entities.
   *
   * @param array $entity_array
   *   An file array to import.
   * @param int $level
   *   The level in the hierarchy of the entity being imported.
   *
   * @return \Drupal\Core\Entity
   *   A full entity object.
   */
  protected function importDefault(array $entity_array, $level) {
    $entity_controller = \Drupal::entityTypeManager()->getStorage($entity_array['entity_type']);
    // Make sure to replace any entity field.
    $entity_prepared = [];
    foreach ($entity_array['representation'] as $field_name => $field_values) {
      foreach ($field_values as $field_key => $field_value) {
        if ($field_key == 'entity_id' && isset($field_value['value']) && isset($this->keyMap[$field_value['value']])) {
          // Then reset keys.
          $entity_prepared[$field_name][$field_key]['value'] = $this->keyMap[$field_value['value']];
        }
        elseif (substr($field_name, 0, 6) != 'field_') {
          $entity_prepared[$field_name] = $field_value[key($field_value)];
        }
        elseif (isset($field_value['target_id']) && isset($this->keyMap[$field_value['target_id']])) {
          if (is_array($this->keyMap[$field_value['target_id']])) {
            // First set other fields.
            $entity_prepared[$field_name][$field_key] = $field_value;
            // Then reset keys.
            $entity_prepared[$field_name][$field_key]['target_id'] = $this->keyMap[$field_value['target_id']]['id'];
            $entity_prepared[$field_name][$field_key]['target_revision_id'] = $this->keyMap[$field_value['target_id']]['revision_id'];
          }
          else {
            // First set other fields.
            $entity_prepared[$field_name][$field_key] = $field_value;
            // Then reset keys.
            $entity_prepared[$field_name][$field_key]['target_id'] = $this->keyMap[$field_value['target_id']];
          }
        }
        else {
          $entity_prepared[$field_name][$field_key] = $field_value;
        }
      }
    }
    unset($entity_prepared['id']);
    $entity_prepared['uid'] = !is_null($this->author) ? $this->author : \Drupal::currentUser()->id();
    unset($entity_prepared['nid']);
    if ($this->removeTimestamps && isset($entity_prepared['created'])) {
      unset($entity_prepared['created']);
    }

    // Set path alias if explicity chosen on the base level.
    if (!$level && $this->uri) {
      $entity_prepared['path'] = [
        'alias' => $this->uri,
        'pathauto' => PathautoState::SKIP,
      ];
    }
    else {
      unset($entity_prepared['path']);
    }

    $entity = $entity_controller->create($entity_prepared);
    $entity->save();
    $this->keyMap[$entity_array['entity_id']] = $entity->id();
    return $entity;
  }

  /**
   * Untar a file.
   *
   * @param string $filepath
   *   The path to the tgz file.
   *
   * @return string
   *   The path to the yml file to use.
   */
  protected function untarFile($filepath) {
    // Make sure that the temp is empty.
    $tmp = $this->fileSystem->getTempDirectory() . '/osk_content';
    if (file_exists($tmp)) {
      $this->fileSystem->deleteRecursive($tmp);
    }
    $this->fileSystem->mkdir($tmp, 0777);
    $filepath = $this->fileSystem->realpath($filepath);
    exec("cd $tmp && tar -xzf $filepath");

    // Find the yaml.
    $this->workingDir = $tmp;
    $working_file = '';
    foreach (scandir($this->workingDir) as $file) {
      if (substr($file, -4) == '.yml') {
        $working_file = $this->workingDir . '/' . $file;
        $this->workingDir .= '/files';
      }
    }
    return $working_file;
  }

}
