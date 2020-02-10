<?php

namespace Drupal\osk_test_content_install\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;

/**
 * Receives and creates VR result sets.
 */
class OskTestContentInstall extends ControllerBase {

  /**
   * Class constructor.
   */
  public function __construct() {

  }

  /**
   * Return the mapping.
   */
  public function showMapping() {
    $input_file = 'public://osk_mapper.json';
    if (!file_exists($input_file)) {
      foreach (glob('../tests/resources/content/*.yml') as $file) {
        $node = osk_content_import_import($file);
        $options = ['absolute' => FALSE, 'attributes' => ['class' => 'this-class']];
        $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)->toString();
        $mapping_id = str_replace(['../tests/resources/content/', '.yml'], '', $file);
        $mapping[$mapping_id]['url'] = $url;
        $mapping[$mapping_id]['id'] = $node->id();
      }
      file_unmanaged_save_data(json_encode($mapping), $input_file, FILE_EXISTS_REPLACE);
    }
    $mapping = json_decode(file_get_contents($input_file));
    return new JsonResponse($mapping);
  }

}
