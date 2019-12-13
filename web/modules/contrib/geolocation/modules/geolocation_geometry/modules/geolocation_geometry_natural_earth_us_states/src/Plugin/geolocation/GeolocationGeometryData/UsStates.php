<?php

namespace Drupal\geolocation_geometry_natural_earth_us_states\Plugin\geolocation\GeolocationGeometryData;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;

/**
 * Import US states.
 *
 * @GeolocationGeometryData(
 *   id = "natural_earth_us_states",
 *   name = @Translation("Natural Earth US States"),
 *   description = @Translation("Geometries of all us states."),
 * )
 */
class UsStates extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $archiveUri = 'http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/110m/cultural/ne_110m_admin_1_states_provinces.zip';

  /**
   * {@inheritdoc}
   */
  public $archiveFilename = 'ne_110m_admin_1_states_provinces.zip';

  /**
   * {@inheritdoc}
   */
  public $shapeDirectory = 'geolocation_geometry_natural_earth_us_states';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'ne_110m_admin_1_states_provinces.shp';

  /**
   * {@inheritdoc}
   */
  public function import() {
    parent::import();
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_us_states');

    try {
      while ($record = $this->shapeFile->getRecord(ShapeFile::GEOMETRY_GEOJSON_GEOMETRY)) {
        if ($record['dbf']['_deleted']) {
          continue;
        }
        else {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $taxonomy_storage->create([
            'vid' => 'geolocation_us_states',
            'name' => utf8_decode($record['dbf']['name']),
          ]);
          $term->set('field_geometry_data_geometry', [
            'geojson' => $record['shp'],
          ]);
          $term->save();
        }
      }
    }
    catch (ShapeFileException $e) {
      $logger->warning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

}
