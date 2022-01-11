<?php

namespace Drupal\osk_content_import;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\osk_content_import\Blobstorage\DigitalOcean;

/**
 * Class OskContentExport.
 */
class OskContentExport {

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
   * Single file or not.
   *
   * @var bool
   */
  protected $singleFile;

  /**
   * Blob export or not.
   *
   * @var bool
   */
  protected $blobExport;

  /**
   * User export or not.
   *
   * @var bool
   */
  protected $uidExport;

  /**
   * An array with all obfuscation.
   *
   * @var array
   */
  protected $obfuscation;

  /**
   * An string with the temporary directory.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * An array with all the files.
   *
   * @var array
   */
  protected $files = [];

  /**
   * A string with the output filename.
   *
   * @var string
   */
  protected $outputFilename = [];

  /**
   * A uuid for all the files to be places in.
   *
   * @var string
   */
  protected $uuid;

  /**
   * A FileSystem instance.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a OskContentExport object.
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
   * Get one entities all dependencies.
   *
   * @param array $entities
   *   An array with entity type, entity id and level in tree.
   * @param bool $blob
   *   Upload the dependencies to a blob server.
   * @param bool $user
   *   Should users be imported.
   * @param array $obfuscated
   *   An array with text fields that should be obfuscated.
   * @param string $filename
   *   A filename for the output file.
   */
  public function export(array $entities, $blob = FALSE, $user = FALSE, array $obfuscated = [], $filename = '') {
    $this->blobExport = $blob;
    $this->uidExport = $user;
    $this->obfuscation = $obfuscated;
    // If no filename exists it creates a 10 character length uuid.
    $this->uuid = $filename ? $filename : substr(md5(microtime(TRUE) . time()), 0, 10);

    $exported_entities = [];

    foreach ($entities as $entity) {
      $exported_entities[] = $this->exportSingleEntity($entity);
    }

    // Create a tmpdir if we don't save to the blob.
    if (!$blob) {
      $this->createTmpDir();
    }

    // Process the files.
    $exported_entities = $this->processFiles($exported_entities);

    $data = Yaml::dump($exported_entities, 20, 2);
    // If it's not blob we put it into the tgz file.
    if (!$blob) {
      file_put_contents($this->tmpDir . '/' . $exported_entities[0]['entity_id'] . '.yml', $data);
      $this->files[] = $this->tmpDir . '/' . $exported_entities[0]['entity_id'] . '.yml';
    }
    else {
      $output_data = $data;
      $this->outputFilename = $this->uuid . '.yml';
    }

    // If it's not blob we tgz the files.
    $output_file = '';
    if (!$blob) {
      $output_file = $this->tmpDir . '/' . $exported_entities[0]['entity_id'] . '.tgz';
      exec("cd $this->tmpDir && tar -czf $output_file *");
      $this->outputFilename = $this->uuid . '.tgz';
    }

    // Start a download.
    $this->downloadContents($data, $output_file);
  }

  /**
   * Make the file available as download.
   *
   * @param string $data
   *   Data for the yaml to create download for.
   * @param string $tgz
   *   File name for the tgz to create download stream for.
   */
  protected function downloadContents($data = '', $tgz = '') {
    // Global headers.
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $this->outputFilename);
    header('Content-Transfer-Encoding: binary');
    if ($tgz) {
      // If a zip exists we stream it.
      header('Content-Type: application/tgz');
      echo $this->streamDownload($tgz);
    }
    else {
      header('Content-Type: text/yaml');
      echo $data;
    }
  }

  /**
   * Stream larger files as download.
   *
   * @param string $filename
   *   Filename or the tgz to create download stream for.
   */
  protected function streamDownload($filename) {
    $buffer = '';
    $cnt = 0;
    $handle = fopen($filename, 'rb');

    if ($handle === FALSE) {
      return FALSE;
    }

    while (!feof($handle)) {
      $buffer = fread($handle, 1024 * 1024);
      echo $buffer;
      if ($buffer) {
        @ob_flush();
        flush();
      }

      $cnt += strlen($buffer);
    }

    $status = fclose($handle);

    if ($status) {
      return $cnt;
    }

    return $status;
  }

  /**
   * Export a single entity.
   *
   * @param array $entity_meta
   *   A array with entity type meta data.
   *
   * @return array
   *   A rendered exported entity array.
   */
  protected function exportSingleEntity(array $entity_meta) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_meta['entity_type'])->load($entity_meta['entity_id']);
    // Get the unique id name for this entity.
    $unique_id = $entity->getEntityType()->getKeys()['id'];

    foreach ($entity as $key => $field) {
      // We don't store any revision data.
      if (substr($key, 0, 9) != 'revision_' && !in_array($key, ['uuid', 'vid'])) {
        $values = $field->getValue();
        // Reset entity type cache for each field.
        $entity_type_cache = '';
        foreach ($values as $value) {
          // Check for dependency fields.
          if (isset($value['target_id'])) {
            // Remove revision.
            if (isset($value['target_revision_id'])) {
              unset($value['target_revision_id']);
            }
            // Figure out what entity it is using the field unless cached.
            if (!$entity_type_cache) {
              $entity_type_cache = $this->getEntityTypeFromReference($entity, $key);
            }

            // Only set a value if it's a content entity, not a config entity.
            if ($entity_type_cache) {
              $value['target_id'] = osk_content_import_hash_id($entity_type_cache, $value['target_id']);;
            }
          }

          // If it's the base id we need to hash it.
          if ($key == $unique_id) {
            $value['value'] = osk_content_import_hash_id($entity_meta['entity_type'], $value['value']);
            $entity_meta['entity_id'] = $value['value'];
          }
          // Let other modules alter the output of the export tree.
          $entity_meta['representation'][$key][] = $value;
        }
      }
    }
    foreach (\Drupal::moduleHandler()->getImplementations('oci_export_entity_alter') as $module) {
      $function = $module . '_oci_export_entity_alter';
      $function($entity_meta);
    }
    return $entity_meta;
  }

  /**
   * Process the files.
   *
   * @param array $exported_entities
   *   All rendered exported entity array.
   *
   * @return array
   *   All rendered exported entity array with pseudo file ids.
   */
  protected function processFiles(array $exported_entities) {
    // First process the files.
    foreach ($exported_entities as $entity_key => $exported_entity) {
      if ($exported_entity['entity_type'] == 'file') {
        foreach ($exported_entity['representation']['uri'] as $file_key => $file) {
          // If we are using blob we send them to the blob, otherwise zip them.
          if ($this->blobExport) {
            $dest_file = $this->uploadToBlobStorage($file['value']);
          }
          else {
            // Get the directory.
            $dest_file = str_replace('public://', '', $file['value']);
            $dest_dir = 'files/' . str_replace(basename($file['value']), '', $dest_file);
            if (!file_exists($this->tmpDir . '/' . $dest_dir)) {
              $this->fileSystem->mkdir($this->tmpDir . '/' . $dest_dir, NULL, TRUE);
            }
            copy($file['value'], $this->tmpDir . '/' . $dest_dir . '/' . basename($file['value']));
            $this->files[] = $this->tmpDir . '/' . $dest_dir . '/' . basename($file['value']);
          }
          $exported_entities[$entity_key]['representation']['uri'][$file_key] = $dest_file;
        }
      }
    }
    return $exported_entities;
  }

  /**
   * Helper function to create an tmp dir.
   */
  protected function createTmpDir() {
    $tmp = $this->fileSystem->getTempDirectory() . '/osk_content';
    if (file_exists($tmp)) {
      $this->fileSystem->deleteRecursive($tmp);
    }
    mkdir($tmp, 0777);
    $this->tmpDir = $this->fileSystem->getTempDirectory() . '/osk_content/' . md5(microtime(TRUE) . time());
    if (!file_exists($this->tmpDir)) {
      $this->fileSystem->mkdir($this->tmpDir, NULL, TRUE);
    }
  }

  /**
   * Get the entity type for any entities reference field.
   *
   * @param mixed $entity
   *   A full entity object.
   * @param string $key
   *   The field reference to check entity type from.
   *
   * @return string|null
   *   The entity type of the referenced field.
   */
  protected function getEntityTypeFromReference($entity, $key) {
    // Load the entity.
    $reference_item = $entity->get($key)->first();
    $sub_entity_item = $reference_item->get('entity');
    $adapter = $sub_entity_item->getTarget();
    $sub_entity = $adapter->getValue();
    // If it's a config entity we return null and use the value.
    if (!method_exists($sub_entity, 'baseFieldDefinitions')) {
      return NULL;
    }

    return $sub_entity->getEntityTypeId();
  }

  /**
   * Upload a file to whatever blob is set.
   *
   * @param string $file
   *   A file path to upload to blob storage.
   *
   * @return string
   *   A pseudo file path on the blob storage.
   */
  protected function uploadToBlobStorage($file) {
    $path = '';
    $config = $this->configFactory->get('osk_content_import.settings');
    switch ($config->get('cloud_blob_type')) {
      case "digital_ocean":
        $storage = new DigitalOcean();
        $path = $storage->upload($file, $this->uuid, $config);
        break;
    }
    return $path;
  }

}
