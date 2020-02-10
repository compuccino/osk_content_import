<?php

/**
 * @file
 * Hooks related to content import.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the array for the form in OskContentEntityExport.
 *
 * @param array $dependencies
 *   The dependencies to add to the entity tree.
 * @param array $previous
 *   Entities that already exists.
 * @param object $entity
 *   The entity.
 * @param string $entity_type
 *   The entity type.
 * @param string $entity_id
 *   The entity id.
 * @param string $bundle_type
 *   The bundle type.
 */
function hook_oci_extend_entity_tree(array &$dependencies, array &$previous, $entity, $entity_type, $entity_id, $bundle_type) {

}

/**
 * Alter export array before it is exported.
 *
 * @param array $entity_export
 *   The raw value of the array that is being exported.
 */
function hook_oci_export_entity_alter(array &$entity_export) {

}

/**
 * @} End of "addtogroup hooks".
 */
