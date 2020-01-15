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
          var options = {};

          switch (featureSettings["position"]) {
            case "right":
              options = {
                position: {
                  top: "108px",
                  right: "10px",
                  bottom: "auto",
                  left: "auto"
                }
              };
              break;

            case "left":
              options = {
                position: {
                  top: "108px",
                  right: "auto",
                  bottom: "auto",
                  left: "10px"
                }
              };
              break;

            case "top":
            case "bottom":
              // I don't know. Leave the default values.
              options = {};
              break;
          }

          map.yandexMap.controls.add('zoomControl', options);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
