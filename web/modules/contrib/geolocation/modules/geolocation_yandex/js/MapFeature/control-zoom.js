/**
 * @file
 * Control Zoom.
 */

(function (Drupal) {

  'use strict';

  /**
   * Zoom control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map zoom functionality to relevant elements.
   */
  Drupal.behaviors.yandexControlZoom = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_zoom',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.yandexMap.controls.add('zoomControl');

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
