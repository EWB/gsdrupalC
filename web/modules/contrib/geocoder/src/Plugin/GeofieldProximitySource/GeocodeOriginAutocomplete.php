<?php

namespace Drupal\geocoder\Plugin\GeofieldProximitySource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\Plugin\GeofieldProximitySource\ManualOriginDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geocoder\ProviderPluginManager;

/**
 * Defines 'Geocode Origin' proximity source plugin.
 *
 * @GeofieldProximitySource(
 *   id = "geofield_geocode_origin_autocomplete",
 *   label = @Translation("Geocode Origin, with Autocomplete"),
 *   description = @Translation("Geocodes origin from free text input, with autocomplete."),
 *   exposedDescription = @Translation("Geocode origin from free text input, with autocomplete."),
 *   exposedOnly = true,
 *   context = {},
 * )
 */
class GeocodeOriginAutocomplete extends ManualOriginDefault implements ContainerFactoryPluginInterface {

  /**
   * Geocoder Plugins not compatible with Geofield Proximity Geocoding.
   *
   * @var array
   */
  protected $incompatiblePlugins = [
    'file',
    'gpxfile',
    'kmlfile',
    'geojsonfile',
  ];

  /**
   * The origin address to geocode and measure proximity from.
   *
   * @var array
   */
  protected $originAddress;

  /**
   * The (minimum) number of terms for the Geocoder to start processing.
   *
   * @var array
   */
  protected $minTerms;

  /**
   * The delay for starting the Geocoder search.
   *
   * @var array
   */
  protected $delay;

  /**
   * Geocoder Control Specific Options.
   *
   * @var array
   */
  protected $options;

  /**
   * The Providers Plugin Manager.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * Constructs a GeocodeOrigin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geocoder\ProviderPluginManager $providerPluginManager
   *   The Providers Plugin Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ProviderPluginManager $providerPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->originAddress = isset($configuration['origin_address']) ? $configuration['origin_address'] : '';
    $this->providerPluginManager = $providerPluginManager;
    $this->minTerms = isset($configuration['settings']['min_terms']) ? $configuration['settings']['min_terms'] : 4;
    $this->delay = isset($configuration['settings']['delay']) ? $configuration['settings']['delay'] : 800;
    $this->options = isset($configuration['settings']['options']) ? $configuration['settings']['options'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geocoder.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE) {

    $lat = isset($this->configuration['origin']['lat']) ? $this->configuration['origin']['lat'] : $this->origin['lat'];
    $lon = isset($this->configuration['origin']['lon']) ? $this->configuration['origin']['lon'] : $this->origin['lon'];

    $form['origin_address'] = [
      '#title' => t('Origin'),
      '#type' => 'textfield',
      '#description' => t('Address, City, Zip-Code, Country, ...'),
      '#default_value' => $this->originAddress,
      '#attributes' => [
        'class' => ['address-input'],
      ],
    ];

    if (!$is_exposed) {
      $form['origin_address']['#title'] = t('Default Origin');
      $form['origin_address']['#description'] = t('Address, City, Zip-Code, Country that would be set as Default Geocoded Address in the Exposed Filter');

      // Attach Geofield Map Library.
      $form['#attached']['library'] = [
        'geocoder/general',
      ];

      $plugins_settings = isset($this->configuration['plugins']) ? $this->configuration['plugins'] : [];

      // Get the enabled/selected plugins.
      $enabled_plugins = [];
      foreach ($plugins_settings as $plugin_id => $plugin) {
        if (!empty($plugin['checked'])) {
          $enabled_plugins[] = $plugin_id;
        }
      }

      // Generates the Draggable Table of Selectable Geocoder Plugins.
      $form['plugins'] = $this->providerPluginManager->providersPluginsTableList($enabled_plugins);

      // Filter out the Geocoder Plugins that are not compatible with Geofield
      // Proximity Geocoding.
      $form['plugins'] = array_filter($form['plugins'], function ($e) {
        return !in_array($e, $this->incompatiblePlugins);
      }, ARRAY_FILTER_USE_KEY);

      // Set a validation for the plugins selection.
      $form['plugins']['#element_validate'] = [[get_class($this), 'validatePluginsSettingsForm']];

      $form['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Geocoder fine Settings'),
        '#open' => FALSE,

      ];

      $form['settings']['min_terms'] = [
        '#type' => 'number',
        '#default_value' => $this->minTerms,
        '#title' => $this->t('The (minimum) number of terms for the Geocoder to start processing.'),
        '#description' => $this->t('Valid values ​​for the widget are between 2 and 10. A too low value (<= 3) will affect the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 2,
        '#max' => 10,
        '#size' => 3,
      ];

      $form['settings']['delay'] = [
        '#type' => 'number',
        '#default_value' => $this->delay,
        '#title' => $this->t('The delay (in milliseconds) between pressing a key in the Address Input field and starting the Geocoder search.'),
        '#description' => $this->t('Valid values ​​for the widget are multiples of 100, between 300 and 3000. A too low value (<= 300) will affect / increase the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 300,
        '#max' => 3000,
        '#step' => 100,
        '#size' => 4,
      ];

      $form['settings']['options'] = [
        '#type' => 'textarea',
        '#rows' => 4,
        '#title' => $this->t('Geocoder Control Specific Options'),
        '#description' => $this->t('This settings would override general Geocoder Providers options. (<u>Note: This would work only for Geocoder 2.x branch/version.</u>)<br>An object literal of specific Geocoder options.The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
        '#default_value' => $this->options,
        '#placeholder' => '{"googlemaps":{"locale": "it", "region": "it"}, "nominatim":{"locale": "it"}}',
        '#element_validate' => [[get_class($this), 'jsonValidate']],
      ];
    }
    else {
      $form['#attributes']['class'][] = 'origin-address-autocomplete';
      $form["origin"] = [
        '#title' => t('Origin Coordinates'),
        '#type' => 'geofield_latlon',
        '#description' => t('Value in decimal degrees. Use dot (.) as decimal separator.'),
        '#default_value' => [
          'lat' => $lat,
          'lon' => $lon,
        ],
        '#attributes' => [
          'class' => ['visually-hidden'],
        ],
      ];
      $form['#attached']['library'] = ['geocoder/geocoder'];
      $form['#attached']['drupalSettings'] = [
        'geocode_origin_autocomplete' => [
          'providers' => array_keys($this->getEnabledProviderPlugins()),
          'minTerms' => $this->minTerms,
          'delay' => $this->delay,
          'options' => $this->options,
        ],
      ];
    }
  }

  /**
   * Get the list of enabled Provider plugins.
   *
   * @return array
   *   Provider plugin IDs and their properties (id, name, arguments...).
   */
  public function getEnabledProviderPlugins() {
    $geocoder_plugins = $this->providerPluginManager->getPlugins();
    $plugins_settings = isset($this->configuration['plugins']) ? $this->configuration['plugins'] : [];

    // Filter out unchecked plugins.
    $provider_plugin_ids = array_filter($plugins_settings, function ($plugin) {
      return isset($plugin['checked']) && $plugin['checked'] == TRUE;
    });

    $provider_plugin_ids = array_combine(array_keys($provider_plugin_ids), array_keys($provider_plugin_ids));

    foreach ($geocoder_plugins as $plugin) {
      if (isset($provider_plugin_ids[$plugin['id']])) {
        $provider_plugin_ids[$plugin['id']] = $plugin;
      }
    }

    return $provider_plugin_ids;
  }

  /**
   * {@inheritdoc}
   */
  public static function validatePluginsSettingsForm(array $element, FormStateInterface &$form_state) {
    $plugins = is_array($element['#value']) ? array_filter($element['#value'], function ($value) {
      return isset($value['checked']) && TRUE == $value['checked'];
    }) : [];

    if (empty($plugins)) {
      $form_state->setError($element, t('The Geocode Origin option needs at least one geocoder plugin selected.'));
    }
  }

  /**
   * Form element json format validation handler.
   *
   * {@inheritdoc}
   */
  public static function jsonValidate($element, FormStateInterface &$form_state) {
    $element_values_array = JSON::decode($element['#value']);
    // Check the jsonValue.
    if (!empty($element['#value']) && $element_values_array == NULL) {
      $form_state->setError($element, t('The @field field is not valid Json Format.', ['@field' => $element['#title']]));
    }
    elseif (!empty($element['#value'])) {
      $form_state->setValueForElement($element, JSON::encode($element_values_array));
    }
  }

}
