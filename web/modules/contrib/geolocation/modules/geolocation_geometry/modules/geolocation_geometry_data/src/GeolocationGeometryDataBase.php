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

  /**
   * URI to archive.
   *
   * @var string
   */
  public $archiveUri = '';

  /**
   * Filename of archive.
   *
   * @var string
   */
  public $archiveFilename = '';

  /**
   * Directory extract of archive.
   *
   * @var string
   */
  public $shapeDirectory = '';

  /**
   * Extracted filename.
   *
   * @var string
   */
  public $shapeFilename = '';

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

    return [
      'title' => t('Import Shapefile'),
      'finished' => [$this, 'finished'],
      'operations' => $operations,
      'progress_message' => t('Finished step @current / @total.'),
      'init_message' => t('Import is starting.'),
      'error_message' => t('Something went horribly wrong.'),
    ];
  }

  /**
   * Download batch callback.
   *
   * @return bool
   *   Batch return.
   */
  public function download() {
    $destination = \Drupal::service('file_system')->getTempDirectory() . '/' . $this->archiveFilename;

    if (!is_file($destination)) {
      $client = \Drupal::httpClient();
      $client->get($this->archiveUri, ['save_to' => $destination]);
    }

    $zip = new \ZipArchive();
    $res = $zip->open($destination);
    if ($res === TRUE) {
      $zip->extractTo(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->shapeDirectory);
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
      $this->shapeFile = new ShapeFile(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->shapeDirectory . '/' . $this->shapeFilename);
    }
    catch (ShapeFileException $e) {
      $logger->warning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

}
