/**
 * @file
 * Fit locations.
 */

(function (Drupal) {

  'use strict';

  Drupal.geolocation = Drupal.geolocation || {};
  Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

  /**
   * @param {GeolocationMapInterface} map
   * @param {GeolocationCenterOption} centerOption
   * @param {Boolean} centerOption.settings.reset_zoom
   */
  Drupal.geolocation.mapCenter.fit_bounds = function (map, centerOption) {
    map.fitMapToMarkers();

    if (centerOption.settings.reset_zoom) {
      map.setZoom(undefined, true);
    }

    return false;
  }

})(Drupal);
