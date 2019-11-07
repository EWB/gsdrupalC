<?php

namespace Drupal\geolocation_geometry\Plugin\views\style;

use Drupal\geolocation\Plugin\views\style\GeolocationStyleBase;

/**
 * Allow to display several field items on a common map.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "geolocation_geometry_layer",
 *   title = @Translation("Geolocation Geometry Layer"),
 *   help = @Translation("Display geometries on a layer."),
 *   theme = "views_view_list",
 *   display_types = {"normal"},
 * )
 */
class GeometryLayer extends GeolocationStyleBase {

  /**
   * {@inheritdoc}
   */
  public function render() {

    $render = parent::render();
    if ($render === FALSE) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $this->displayHandler->display['id'],
        'class' => [
          'geolocation-layer',
        ],
      ],
    ];

    /*
     * Add locations to output.
     */
    foreach ($this->view->result as $row_number => $row) {
      foreach ($this->getLocationsFromRow($row) as $location) {
        $build['locations'][] = $location;
      }
    }

    return $build;
  }

}
