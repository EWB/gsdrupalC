<?php

namespace Drupal\geolocation_geometry_natural_earth_countries\Plugin\geolocation\GeolocationGeometryData;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;

/**
 * Import Countries of the world.
 *
 * @GeolocationGeometryData(
 *   id = "natural_earth_countries",
 *   name = @Translation("Natural Earth Countries"),
 *   description = @Translation("Geometries of all countries of the world."),
 * )
 */
class NaturalEarthCountries extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $archiveUri = 'http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/110m/cultural/ne_110m_admin_0_countries.zip';

  /**
   * {@inheritdoc}
   */
  public $archiveFilename = 'ne_110m_admin_0_countries.zip';

  /**
   * {@inheritdoc}
   */
  public $shapeDirectory = 'geolocation_geometry_natural_earth_countries';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'ne_110m_admin_0_countries.shp';

  /**
   * {@inheritdoc}
   */
  public function import() {
    parent::import();
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_geometry_natural_earth_countries');

    try {
      while ($record = $this->shapeFile->getRecord(ShapeFile::GEOMETRY_GEOJSON_GEOMETRY)) {
        if ($record['dbf']['_deleted']) {
          continue;
        }
        else {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $taxonomy_storage->create([
            'vid' => 'geolocation_geometry_countries',
            'name' => utf8_decode($record['dbf']['NAME']),
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
