<?php

namespace Drupal\geolocation_geometry_data;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;

/**
 * Class ShapeFileImportBatch.
 *
 * @package Drupal\geolocation_geometry_data
 */
abstract class GeolocationGeometryDataBase {

  public $archiveUri = '';
  public $archiveFilename = '';
  public $shapeDirectory = '';
  public $shapeFilename = '';
  public $configDirectory = '';

  /**
   * Shape file.
   *
   * @var \ShapeFile\ShapeFile|null
   */
  public $shapeFile;

  /**
   * Return this batch.
   *
   * @return array
   *   Batch return.
   */
  public function getBatch() {
    $operations = [
      [[$this, 'download'], []],
      [[$this, 'import'], []],
    ];

    $batch = [
      'title' => t('Import Shapefile'),
      'finished' => [$this, 'finished'],
      'operations' => $operations,
      'progress_message' => t('Finished step @current / @total.'),
      'init_message' => t('Import is starting.'),
      'error_message' => t('Something went horribly wrong.'),
    ];
    return $batch;
  }

  /**
   * Download batch callback.
   *
   * @return bool
   *   Batch return.
   */
  public function download() {
    $destination = file_directory_temp() . '/' . $this->archiveFilename;

    if (!is_file($destination)) {
      $client = \Drupal::httpClient();
      $client->get($this->archiveUri, ['save_to' => $destination]);
    }

    $zip = new \ZipArchive();
    $res = $zip->open($destination);
    if ($res === TRUE) {
      $zip->extractTo(file_directory_temp() . '/' . $this->shapeDirectory);
      $zip->close();
    }
    else {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Import batch callback.
   *
   * @return bool
   *   Batch return.
   */
  public function import() {
    $logger = \Drupal::logger('geolocation_geometry_natural_earth_us_states');

    try {
      $this->shapeFile = new ShapeFile(file_directory_temp() . '/' . $this->shapeDirectory . '/' . $this->shapeFilename);
    }
    catch (ShapeFileException $e) {
      $logger->warning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

}
