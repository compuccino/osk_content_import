<?php

namespace Drupal\osk_content_import\Blobstorage;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Class DigitalOcean.
 */
class DigitalOcean {

  /**
   * Upload one file.
   *
   * @param string $file_path
   *   An array with entity type, entity id and level in tree.
   * @param string $uuid
   *   An UUID to create a folder from.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   All configuration values specific for this type of upload.
   *
   * @return string
   *   Returns a string with the pseudo path.
   */
  public function upload($file_path, $uuid, ImmutableConfig $config) {
    // Get the config and connect.
    $space_url = $config->get('digitalocean_url');
    $parts = explode('.', str_replace(['https://', '.digitaloceanspaces.com'], '', $space_url));
    if (isset($parts[1])) {
      // Set the Digital Ocean path.
      $do_path = $config->get('digitalocean_prefix') . '/test-material/' . $uuid . '/' . str_replace('public://', '', $file_path);
      $space = new \SpacesConnect($config->get('digitalocean_key'), $config->get('digitalocean_secret'), trim($parts[0]), trim($parts[1]));
      $spaces = $space->ListSpaces();
      $space->UploadFile($file_path, 'private', $do_path);
    }
    return 'digitalocean://' . $do_path;
  }

  /**
   * Download one file.
   *
   * @param string $file_path
   *   The file path for the digitalocean download.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   All configuration values specific for this type of upload.
   *
   * @return array
   *   An array with the file data and the path.
   */
  public function download($file_path, ImmutableConfig $config) {
    $path = str_replace('digitalocean://', '', $file_path);
    $file_parts = explode('/', $path);
    unset($file_parts[0]);
    unset($file_parts[1]);
    unset($file_parts[2]);
    $real_path = implode('/', array_values($file_parts));
    $cache_dir = $config->get('cloud_cache_dir');
    $filesystem = \Drupal::service('file_system');
    // Check cache and return.
    $savefile = file_build_uri($cache_dir . '/' . $real_path);
    if (!$cache_dir || !file_exists($savefile)) {
      // Get the config and connect.
      $space_url = $config->get('digitalocean_url');
      $parts = explode('.', str_replace(['https://', '.digitaloceanspaces.com'], '', $space_url));
      if (isset($parts[1])) {
        // Set the Digital Ocean path.
        $savefile = $cache_dir ? file_build_uri($cache_dir . '/' . $real_path) : file_directory_temp() . '/tmpfile';
        $space = new \SpacesConnect($config->get('digitalocean_key'), $config->get('digitalocean_secret'), trim($parts[0]), trim($parts[1]));
        // Create dir(s) for cache.
        if ($cache_dir && count($file_parts) > 1) {
          $base_dir = $filesystem->realpath(file_build_uri($cache_dir));
          if (!file_exists($base_dir)) {
            mkdir($base_dir, 0777);
          }
          $i = 1;
          foreach ($file_parts as $extra_path) {
            if ($i != count($file_parts)) {
              $base_dir .= '/' . $extra_path;
              if (!file_exists($base_dir)) {
                mkdir($base_dir, 0777);
              }
              $i++;
            }
          }
        }
        $i = 0;
        while ($i != 3) {
          try {
            $space->DownloadFile($path, $savefile);
            $i = 3;
          }
          catch (Exception $e) {
            $i++;
          }
        }
      }
    }

    return ['file' => $savefile, 'path' => file_build_uri($real_path)];
  }

}
