<?php

namespace Drupal\geolocation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\MapCenterManager;
use Drupal\geolocation\MapProviderManager;
use Drupal\filter\Entity\FilterFormat;

/**
 * Exposes a map rendered as a block.
 *
 * @Block(
 *   id = "geolocation_block",
 *   admin_label = @Translation("Geolocation Map")
 * )
 */
class GeolocationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Map Provider.
   *
   * @var \Drupal\geolocation\MapProviderManager
   */
  protected $mapProviderManager = NULL;

  /**
   * MapCenter options manager.
   *
   * @var \Drupal\geolocation\MapCenterManager
   */
  protected $mapCenterManager = NULL;

  /**
   * Construct a GeoocationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\MapProviderManager $map_provider_manager
   *   The map provider manager.
   * @param \Drupal\geolocation\MapCenterManager $map_center_manager
   *   The map center manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MapProviderManager $map_provider_manager, MapCenterManager $map_center_manager) {
    $this->mapProviderManager = $map_provider_manager;
    $this->mapCenterManager = $map_center_manager;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.mapprovider'),
      $container->get('plugin.manager.geolocation.mapcenter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['map_provider_id'] = '';
    if (\Drupal::moduleHandler()->moduleExists('geolocation_google_maps')) {
      $configuration['map_provider_id'] = 'google_maps';
    }
    elseif (\Drupal::moduleHandler()->moduleExists('geolocation_leaflet')) {
      $configuration['map_provider_id'] = 'leaflet';
    }
    $configuration['map_provider_settings'] = [];

    $configuration['centre'] = [];
    $configuration['locations'] = [];
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['locations'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Markers'),
      '#attributes' => [
        'id' => 'block-locations',
      ],
    ];

    for ($i = 0; $i < count($this->configuration['locations']); $i++) {

      $form['locations'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Marker %index', ['%index' => $i]),
        'marker_title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Marker title'),
          '#description' => $this->t('When the cursor hovers on the marker, this title will be shown as description.'),
          '#default_value' => empty($this->configuration['locations'][$i]['marker_title']) ? '' : $this->configuration['locations'][$i]['marker_title'],
        ],
        'marker_content' => [
          '#type' => 'text_format',
          '#title' => $this->t('Marker info text'),
          '#description' => $this->t('When the marker is clicked, this text will be shown in a popup above it. Leave blank to not display. Token replacement supported.'),
        ],
        'marker_coordinates' => [
          '#type' => 'geolocation_input',
          '#title' => t('Marker Coordinates'),
          '#default_value' => empty($this->configuration['locations'][$i]['marker_coordinates']) ? '' : $this->configuration['locations'][$i]['marker_coordinates'],
        ],
      ];

      if (!empty($this->configuration['locations'][$i]['marker_content']['value'])) {
        $form['locations'][$i]['marker_content']['#default_value'] = $this->configuration['locations'][$i]['marker_content']['value'];
      }

      if (!empty($this->configuration['locations'][$i]['marker_content']['format'])) {
        $form['locations'][$i]['marker_content']['#format'] = $this->configuration['locations'][$i]['marker_content']['format'];
      }
    }

    $form['locations']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Add location'),
      '#submit' => [[$this, 'addLocationSubmit']],
      '#ajax'   => [
        'callback' => [$this, 'addLocation'],
        'wrapper'  => 'block-locations',
        'effect' => 'fade',
      ],
    ];

    $map_provider_options = $this->mapProviderManager->getMapProviderOptions();

    if (empty($map_provider_options)) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => t("No map provider found."),
      ];
    }

    $form['centre'] = $this->mapCenterManager->getCenterOptionsForm((array) $this->configuration['centre'], ['formatter' => $this]);

    $form['map_provider_id'] = [
      '#type' => 'select',
      '#options' => $map_provider_options,
      '#title' => $this->t('Map Provider'),
      '#default_value' => $this->configuration['map_provider_id'],
      '#ajax' => [
        'callback' => [get_class($this->mapProviderManager), 'addSettingsFormAjax'],
        'wrapper' => 'map-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $form['map_provider_settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => t("No settings available."),
    ];

    $parents = [
      'settings',
    ];

    $map_provider_id = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, ['map_provider_id']));
    if (empty($map_provider_id)) {
      $map_provider_id = $this->configuration['map_provider_id'];
    }
    if (empty($map_provider_id)) {
      $map_provider_id = key($map_provider_options);
    }

    $map_provider_settings = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, ['map_provider_settings']));
    if (empty($map_provider_settings)) {
      $map_provider_settings = $this->configuration['map_provider_settings'];
    }

    if (!empty($map_provider_id)) {
      $form['map_provider_settings'] = $this->mapProviderManager
        ->createInstance($map_provider_id, $map_provider_settings)
        ->getSettingsForm(
          $map_provider_settings,
          array_merge($parents, ['map_provider_settings'])
        );
    }

    $form['map_provider_settings'] = array_replace(
      $form['map_provider_settings'],
      [
        '#prefix' => '<div id="map-provider-settings">',
        '#suffix' => '</div>',
      ]
    );

    return $form;
  }

  /**
   * Add location.
   *
   * @param array $form
   *   Current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public function addLocationSubmit(array &$form, FormStateInterface &$form_state) {
    $this->configuration['locations'][] = [
      'marker_title' => '',
      'marker_content' => [
        'value' => '',
        'format' => filter_default_format(),
      ],
      'marker_coordinates' => [],
    ];
    $form_state->setRebuild();
  }

  /**
   * Add location.
   *
   * @param array $form
   *   Current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Render array.
   */
  public function addLocation(array &$form, FormStateInterface &$form_state) {
    return $form['settings']['locations'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['map_provider_id'] = $form_state->getValue('map_provider_id');
    $this->configuration['map_provider_settings'] = $form_state->getValue('map_provider_settings');
    $this->configuration['centre'] = $form_state->getValue('centre');

    $this->configuration['locations'] = [];
    foreach ($form_state->getValue('locations') as $index => $location) {
      if (
        !empty($location['marker_title'])
        && !empty($location['marker_content'])
        && !empty($location['marker_coordinates'])
      ) {
        $this->configuration['locations'][] = [
          'marker_title' => $location['marker_title'],
          'marker_content' => $location['marker_content'],
          'marker_coordinates' => $location['marker_coordinates'],
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#id' => uniqid("map-"),
      '#type' => 'geolocation_map',
      '#settings' => $this->configuration['map_provider_settings'],
      '#maptype' => $this->configuration['map_provider_id'],
      '#centre' => [],
      '#context' => ['block' => $this],
    ];

    foreach ($this->configuration['locations'] as $index => $location) {
      $build[$index] = [
        '#type' => 'geolocation_map_location',
        '#title' => $location['marker_title'],
        '#position' => $location['marker_coordinates'],
        'content' => [
          '#type' => 'processed_text',
          '#text' => $location['marker_content']['value'],
          '#format' => $location['marker_content']['format'],
        ],
      ];
    }

    $build = $this->mapCenterManager->alterMap($build, $this->configuration['centre'], ['block' => $this]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach ($this->configuration['locations'] as $index => $location) {
      $filter_format = FilterFormat::load($location['marker_content']['format']);
      $dependencies['config'][] = $filter_format->getConfigDependencyName();
    }
    return $dependencies;
  }

}
