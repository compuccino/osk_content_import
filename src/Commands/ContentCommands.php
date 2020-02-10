<?php

// @codingStandardsIgnoreStart
// Drush uses a special standard of comments that does not conform to drupal standard

namespace Drupal\osk_content_import\Commands;

use Drush\Commands\DrushCommands;

/**
 * Defines the commands for Drush.
 */
class ContentCommands extends DrushCommands {

  /**
   * Content Import
   *
   * @command osk_content:import
   * @param $file A path to a yml file
   * @aliases oski
   * @usage drush osk_content:import test.yml
   *   Imports all files from test.yml
   */
  public function import($file) {
    if (!file_exists($file)) {
      throw new \Exception(dt('File not found'));
    } else {
      $label = osk_content_import_import($file, $options['uri'], $options['uid'], $options['remove_timestamp']);
      return "$label was created";
    }
  }
}
