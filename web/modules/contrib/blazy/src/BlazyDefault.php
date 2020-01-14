<?php

namespace Drupal\blazy;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 */
class BlazyDefault {

  /**
   * The supported $breakpoints.
   *
   * @var array
   */
  private static $breakpoints = ['xs', 'sm', 'md', 'lg', 'xl'];

  /**
   * Defines constant for the supported text tags.
   */
  const TAGS = ['a', 'em', 'strong', 'h2', 'p', 'span', 'ul', 'ol', 'li'];

  /**
   * The current class instance.
   *
   * @var self
   */
  private static $instance = NULL;

  /**
   * Returns the static instance of this class.
   */
  public static function getInstance() {

    if (is_null(self::$instance)) {
      self::$instance = new BlazyDefault();
    }

    return self::$instance;
  }

  /**
   * Returns Blazy specific breakpoints.
   */
  public static function getConstantBreakpoints() {
    return self::$breakpoints;
  }

  /**
   * Returns alterable plugin settings to pass the tests.
   */
  public function alterableSettings(array &$settings = []) {
    $context = ['class' => get_called_class()];
    \Drupal::moduleHandler()->alter('blazy_base_settings', $settings, $context);

    return $settings;
  }

  /**
   * Returns basic plugin settings.
   */
  public static function baseSettings() {
    $settings = [
      'cache'             => 0,
      'current_view_mode' => '',
      'optionset'         => 'default',
      'skin'              => '',
      'style'             => '',
    ];

    blazy_alterable_settings($settings);
    return $settings;
  }

  /**
   * Returns image-related field formatter and Views settings.
   */
  public static function baseImageSettings() {
    return [
      'background'             => FALSE,
      'box_caption'            => '',
      'box_caption_custom'     => '',
      'box_style'              => '',
      'box_media_style'        => '',
      'breakpoints'            => [],
      'caption'                => [],
      'image_style'            => '',
      'media_switch'           => '',
      'ratio'                  => '',
      'responsive_image_style' => '',
      'sizes'                  => '',
    ];
  }

  /**
   * Returns image-related field formatter and Views settings.
   */
  public static function imageSettings() {
    return [
      'icon'            => '',
      'layout'          => '',
      'thumbnail_style' => '',
      'view_mode'       => '',
    ] + self::baseSettings() + self::baseImageSettings();
  }

  /**
   * Returns Views specific settings.
   */
  public static function viewsSettings() {
    return [
      'class'   => '',
      'id'      => '',
      'image'   => '',
      'link'    => '',
      'overlay' => '',
      'title'   => '',
      'vanilla' => FALSE,
    ];
  }

  /**
   * Returns fieldable entity formatter and Views settings.
   */
  public static function extendedSettings() {
    return self::viewsSettings() + self::imageSettings();
  }

  /**
   * Returns optional grid field formatter and Views settings.
   */
  public static function gridSettings() {
    return [
      'grid'        => 0,
      'grid_header' => '',
      'grid_medium' => 0,
      'grid_small'  => 0,
      'style'       => '',
    ];
  }

  /**
   * Returns sensible default options common for Views lacking of UI.
   */
  public static function lazySettings() {
    return [
      'blazy' => TRUE,
      'lazy'  => 'blazy',
      'ratio' => 'fluid',
    ];
  }

  /**
   * Returns sensible default options common for entities lacking of UI.
   */
  public static function entitySettings() {
    return [
      'media_switch' => 'media',
      'rendered'     => FALSE,
      'view_mode'    => 'default',
      '_detached'    => TRUE,
    ] + self::lazySettings();
  }

  /**
   * Returns sensible default container settings to shutup notices when lacking.
   */
  public static function htmlSettings() {
    return [
      'blazy_data' => [],
      'lightbox'   => FALSE,
      'namespace'  => 'blazy',
      'id'         => '',
    ] + self::imageSettings();
  }

  /**
   * Returns sensible default item settings to shutup notices when lacking.
   */
  public static function itemSettings() {
    return [
      '_api'           => FALSE,
      'content_url'    => '',
      'delta'          => 0,
      'embed_url'      => '',
      'entity_type_id' => '',
      'extension'      => '',
      'image_url'      => '',
      'item_id'        => 'blazy',
      'lazy_attribute' => 'src',
      'lazy_class'     => 'b-lazy',
      'one_pixel'      => TRUE,
      'placeholder'    => '',
      'padding_bottom' => '',
      'player'         => FALSE,
      'resimage'       => FALSE,
      'scheme'         => '',
      'type'           => 'image',
      'uri'            => '',
      'use_data_uri'   => FALSE,
      'use_loading'    => TRUE,
      'use_media'      => FALSE,
      'height'         => NULL,
      'width'          => NULL,
    ] + self::htmlSettings();
  }

  /**
   * Returns blazy theme properties, its image and container attributes.
   */
  public static function themeProperties() {
    return [
      'attributes',
      'captions',
      'image',
      'item',
      'item_attributes',
      'settings',
      'url',
    ];
  }

  /**
   * Returns additional/ optional blazy theme attributes.
   */
  public static function themeAttributes() {
    return ['caption', 'media', 'url', 'wrapper'];
  }

}
