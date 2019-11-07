<?php

/**
 * @file
 * Provide views data for geolocation_geometry.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function geolocation_geometry_field_views_data(FieldStorageConfigInterface $field_storage) {
  // Make sure views.views.inc is loaded.
  module_load_include('inc', 'views', 'views.views');

  // Get the default data from the views module.
  $data = views_field_default_views_data($field_storage);

  $args = ['@field_name' => $field_storage->getName()];

  // Loop through all of the results and set our overrides.
  foreach ($data as $table_name => $table_data) {
    foreach ($table_data as $field_name => $field_data) {
      // Only modify fields.
      if ($field_name != 'delta') {
        if (isset($field_data['field'])) {
          // Use our own field handler.
          $data[$table_name][$field_name]['field']['id'] = 'geolocation_geometry_field';
          $data[$table_name][$field_name]['field']['click sortable'] = FALSE;
        }

        // The default filters are not useful.
        unset($data[$table_name][$field_name]['filter']);

        // The default arguments aren't useful at all so remove them.
        unset($data[$table_name][$field_name]['argument']);

        // The default arguments aren't useful at all so remove them.
        unset($data[$table_name][$field_name]['sort']);
      }
    }

    $field_coordinates_table_data = [];
    $entity_type_id = $field_storage->getTargetEntityTypeId();
    $target_entity_type = \Drupal::entityTypeManager()->getDefinition($field_storage->getTargetEntityTypeId());

    if (array_key_exists($target_entity_type->getBaseTable() . '__' . $field_storage->getName(), $data)) {
      $field_coordinates_table_data = $data[$target_entity_type->getBaseTable() . '__' . $field_storage->getName()][$field_storage->getName()];
    }
    elseif (array_key_exists($entity_type_id . '__' . $field_storage->getName(), $data)) {
      // Fall back to using the key format as defined in,
      // views_field_default_views_data().
      $field_coordinates_table_data = $data[$entity_type_id . '__' . $field_storage->getName()][$field_storage->getName()];
    }

    // Add proximity handlers.
    $data[$table_name][$args['@field_name'] . '_proximity'] = [
      'group' => $target_entity_type->getLabel(),
      'title' => t('Proximity (@field_name)', $args),
      'title short' => isset($field_coordinates_table_data['title short']) ? $field_coordinates_table_data['title short'] . t(":proximity") : '',
      'help' => isset($field_coordinates_table_data['help']) ? $field_coordinates_table_data['help'] : '',
      'argument' => [
        'id' => 'geolocation_argument_proximity',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
      'filter' => [
        'id' => 'geolocation_filter_proximity',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
      'field' => [
        'table' => $table_name,
        'id' => 'geolocation_field_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $entity_type_id,
        'real field' => $args['@field_name'],
        'float' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
        'element type' => 'div',
        'is revision' => (isset($table_data[$args['@field_name']]['field']['is revision']) && $table_data[$args['@field_name']]['field']['is revision']),
        'click sortable' => TRUE,
      ],
      'sort' => [
        'table' => $table_name,
        'id' => 'geolocation_sort_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $entity_type_id,
        'real field' => $args['@field_name'],
      ],
    ];

    // Add boundary handlers.
    $data[$table_name][$args['@field_name'] . '_boundary'] = [
      'group' => $target_entity_type->getLabel(),
      'title' => t('Boundary (@field_name)', $args),
      'title short' => isset($field_coordinates_table_data['title short']) ? $field_coordinates_table_data['title short'] . t(":boundary") : '',
      'help' => isset($field_coordinates_table_data['help']) ? $field_coordinates_table_data['help'] : '',
      'filter' => [
        'id' => 'geolocation_filter_boundary',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $args['@field_name'] . '_boundary',
        'real field' => $args['@field_name'],
        'label' => t('Boundaries around !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
        ],
      ],
    ];
  }

  return $data;
}
