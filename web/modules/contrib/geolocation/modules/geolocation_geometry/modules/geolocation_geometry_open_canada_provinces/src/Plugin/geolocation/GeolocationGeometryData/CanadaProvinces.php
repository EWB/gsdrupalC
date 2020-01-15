<?php

namespace Drupal\geolocation_geometry_open_canada_provinces\Plugin\geolocation\GeolocationGeometryData;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;

/**
 * Import Provinces of Canada.
 *
 * @GeolocationGeometryData(
 *   id = "open_canada_provinces",
 *   name = @Translation("Provinces of Canada"),
 *   description = @Translation("Geometries of all us states."),
 * )
 */
class CanadaProvinces extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $archiveUri = 'http://ftp.maps.canada.ca/pub/nrcan_rncan/vector/canvec/shp/Admin/canvec_15M_CA_Admin_shp.zip';

  /**
   * {@inheritdoc}
   */
  public $archiveFilename = 'canvec_15M_CA_Admin_shp.zip';

  /**
   * {@inheritdoc}
   */
  public $shapeDirectory = 'geolocation_geometry_open_canadian_provinces';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'canvec_15M_CA_Admin/geo_political_region_2.shp';

  /**
   * {@inheritdoc}
   */
  public function import() {
    parent::import();
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_provinces_of_canada');

    try {
      while ($record = $this->shapeFile->getRecord(ShapeFile::GEOMETRY_GEOJSON_GEOMETRY)) {
        if ($record['dbf']['_deleted']) {
          continue;
        }
        else {
          if (utf8_decode($record['dbf']['ctry_en']) !== 'Canada') {
            continue;
          }
          $name = utf8_decode($record['dbf']['juri_en']);
          if (empty($name)) {
            continue;
          }
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $taxonomy_storage->create([
            'vid' => 'geolocation_provinces_of_canada',
            'name' => $name,
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
