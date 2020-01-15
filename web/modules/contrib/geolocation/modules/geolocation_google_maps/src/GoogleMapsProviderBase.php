<?php

namespace Drupal\geolocation_google_maps;

use Drupal\Core\Url;
use Drupal\geolocation\MapProviderBase;

/**
 * Class GoogleMapsProviderBase.
 *
 * @package Drupal\geolocation_google_maps
 */
abstract class GoogleMapsProviderBase extends MapProviderBase {

  /**
   * Google map style - Roadmap.
   *
   * @var string
   */
  public static $ROADMAP = 'ROADMAP';

  /**
   * Google map style - Satellite.
   *
   * @var string
   */
  public static $SATELLITE = 'SATELLITE';

  /**
   * Google map style - Hybrid.
   *
   * @var string
   */
  public static $HYBRID = 'HYBRID';

  /**
   * Google map style - Terrain.
   *
   * @var string
   */
  public static $TERRAIN = 'TERRAIN';

  /**
   * Google maps url.
   *
   * @var string
   */
  public static $GOOGLEMAPSAPIURLBASE = 'https://maps.googleapis.com';

  /**
   * Google maps url from PR China.
   *
   * @var string
   */
  public static $GOOGLEMAPSAPIURLBASECHINA = 'https://maps.google.cn';

  /**
   * Google maps url from PR China.
   *
   * @var string
   */
  public static $GOOGLEMAPSAPIURLPATH = '/maps/api';

  /**
   * Return all module and custom defined parameters.
   *
   * @param array $additional_parameters
   *   Additional parameters.
   *
   * @return array
   *   Parameters
   */
  public function getGoogleMapsApiParameters(array $additional_parameters = []) {
    $config = \Drupal::config('geolocation_google_maps.settings');
    $geolocation_parameters = [
      'key' => $config->get('google_map_api_key'),
    ];

    $module_parameters = \Drupal::moduleHandler()->invokeAll('geolocation_google_maps_parameters') ?: [];
    $custom_parameters = $config->get('google_map_custom_url_parameters') ?: [];

    // Set the map language to site language if desired and possible.
    if ($config->get('use_current_language') && \Drupal::moduleHandler()->moduleExists('language')) {
      $custom_parameters['language'] = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $parameters = array_replace_recursive($additional_parameters, $custom_parameters, $module_parameters, $geolocation_parameters);

    foreach ($parameters as $key => $value) {
      if (
        is_string($value)
        && $value === ''
      ) {
        unset($parameters[$key]);
      }
    }

    return $parameters;
  }

  /**
   * Return the fully build URL to load Google Maps API.
   *
   * @param array $additional_parameters
   *   Additional parameters.
   *
   * @return string
   *   Google Maps API URL
   */
  public function getGoogleMapsApiUrl(array $additional_parameters = []) {
    $config = \Drupal::config('geolocation_google_maps.settings');

    if (!empty($config->get('google_maps_base_url'))) {
      $google_url = $config->get('google_maps_base_url');
    }
    elseif ($config->get('china_mode')) {
      $google_url = static::$GOOGLEMAPSAPIURLBASECHINA;
    }
    else {
      $google_url = static::$GOOGLEMAPSAPIURLBASE;
    }

    $parameters = [];
    foreach ($this->getGoogleMapsApiParameters($additional_parameters) as $parameter => $value) {
      $parameters[$parameter] = is_array($value) ? implode(',', $value) : $value;
    }
    $url = Url::fromUri($google_url . static::$GOOGLEMAPSAPIURLPATH, [
      'query' => $parameters,
      'https' => TRUE,
    ]);
    return $url->toString();
  }

  /**
   * An array of all available map types.
   *
   * @return array
   *   The map types.
   */
  private function getMapTypes() {
    $mapTypes = [
      static::$ROADMAP => 'Road map view',
      static::$SATELLITE => 'Google Earth satellite images',
      static::$HYBRID => 'A mixture of normal and satellite views',
      static::$TERRAIN => 'A physical map based on terrain information',
    ];

    return array_map([$this, 't'], $mapTypes);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'type' => static::$ROADMAP,
        'zoom' => 10,
        'height' => '400px',
        'width' => '100%',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings) {
    $settings = parent::getSettings($settings);

    $settings['zoom'] = (int) $settings['zoom'];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings) {
    $types = $this->getMapTypes();
    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );
    $summary = parent::getSettingsSummary($settings);
    $summary[] = $this->t('Map Type: @type', ['@type' => $types[$settings['type']]]);
    $summary[] = $this->t('Zoom level: @zoom', ['@zoom' => $settings['zoom']]);
    $summary[] = $this->t('Height: @height', ['@height' => $settings['height']]);
    $summary[] = $this->t('Width: @width', ['@width' => $settings['width']]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []) {
    $settings = $this->getSettings($settings);
    $parents_string = '';
    if ($parents) {
      $parents_string = implode('][', $parents) . '][';
    }

    $form = parent::getSettingsForm($settings, $parents);

    /*
     * General settings.
     */
    $form['general_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General'),
    ];
    $form['height'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['height'],
    ];
    $form['width'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['width'],
    ];
    $form['type'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'select',
      '#title' => $this->t('Default map type'),
      '#options' => $this->getMapTypes(),
      '#default_value' => $settings['type'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['zoom'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'number',
      '#title' => $this->t('Zoom level'),
      '#description' => $this->t('The initial resolution at which to display the map, where zoom 0 corresponds to a map of the Earth fully zoomed out, and higher zoom levels zoom in at a higher resolution up to 20 for streetlevel.'),
      '#default_value' => $settings['zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    if ($parents_string) {
      $form['zoom']['#group'] = $parents_string . 'general_settings';
    }

    return $form;
  }

}
