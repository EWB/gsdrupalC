<?php

namespace Drupal\slick;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\slick\Entity\Slick;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyManagerBase;

/**
 * Implements BlazyManagerInterface, SlickManagerInterface.
 */
class SlickManager extends BlazyManagerBase implements SlickManagerInterface {

  /**
   * The supported skins.
   *
   * @var array
   */
  private static $skins = [
    'browser',
    'lightbox',
    'overlay',
    'main',
    'thumbnail',
    'arrows',
    'dots',
    'widget',
  ];

  /**
   * Static cache for the skin definition.
   *
   * @var array
   */
  protected $skinDefinition;

  /**
   * Static cache for the skins by group.
   *
   * @var array
   */
  protected $skinsByGroup;

  /**
   * The easing libray.
   *
   * @var string|bool
   */
  protected $easingPath;

  /**
   * The library info definition.
   *
   * @var array
   */
  protected $libraryInfoBuild;

  /**
   * Returns the supported skins.
   */
  public static function getConstantSkins() {
    return self::$skins;
  }

  /**
   * Returns slick skins registered via hook_slick_skins_info(), or defaults.
   *
   * @see \Drupal\blazy\BlazyManagerBase::buildSkins()
   */
  public function getSkins() {
    if (!isset($this->skinDefinition)) {
      $methods = ['skins', 'arrows', 'dots'];
      $this->skinDefinition = $this->buildSkins('slick', '\Drupal\slick\SlickSkin', $methods);
    }

    return $this->skinDefinition;
  }

  /**
   * Returns available slick skins by group.
   */
  public function getSkinsByGroup($group = '', $option = FALSE) {
    if (!isset($this->skinsByGroup[$group])) {
      $skins         = $groups = $ungroups = [];
      $nav_skins     = in_array($group, ['arrows', 'dots']);
      $defined_skins = $nav_skins ? $this->getSkins()[$group] : $this->getSkins()['skins'];

      foreach ($defined_skins as $skin => $properties) {
        $item = $option ? strip_tags($properties['name']) : $properties;
        if (!empty($group)) {
          if (isset($properties['group'])) {
            if ($properties['group'] != $group) {
              continue;
            }
            $groups[$skin] = $item;
          }
          elseif (!$nav_skins) {
            $ungroups[$skin] = $item;
          }
        }
        $skins[$skin] = $item;
      }

      $this->skinsByGroup[$group] = $group ? array_merge($ungroups, $groups) : $skins;
    }
    return $this->skinsByGroup[$group];
  }

  /**
   * Implements hook_library_info_build().
   */
  public function libraryInfoBuild() {
    if (!isset($this->libraryInfoBuild)) {
      $libraries['slick.css'] = [
        'dependencies' => ['slick/slick'],
        'css' => [
          'theme' => ['/libraries/slick/slick/slick-theme.css' => ['weight' => -2]],
        ],
      ];

      foreach (self::getConstantSkins() as $group) {
        if ($skins = $this->getSkinsByGroup($group)) {
          foreach ($skins as $key => $skin) {
            $provider = isset($skin['provider']) ? $skin['provider'] : 'slick';
            $id = $provider . '.' . $group . '.' . $key;

            foreach (['css', 'js', 'dependencies'] as $property) {
              if (isset($skin[$property]) && is_array($skin[$property])) {
                $libraries[$id][$property] = $skin[$property];
              }
            }
          }
        }
      }

      $this->libraryInfoBuild = $libraries;
    }

    return $this->libraryInfoBuild;
  }

  /**
   * Returns easing library path if available, else FALSE.
   */
  public function getEasingPath() {
    if (!isset($this->easingPath)) {
      if (function_exists('libraries_get_path')) {
        $library_easing = libraries_get_path('easing') ?: libraries_get_path('jquery.easing');
        if ($library_easing) {
          $easing_path = $library_easing . '/jquery.easing.min.js';
          // Composer via bower-asset puts the library within `js` directory.
          if (!is_file($easing_path)) {
            $easing_path = $library_easing . '/js/jquery.easing.min.js';
          }
        }
      }
      else {
        $easing_path = DRUPAL_ROOT . '/libraries/easing/jquery.easing.min.js';
      }
      $this->easingPath = isset($easing_path) && is_file($easing_path) ? $easing_path : FALSE;
    }
    return $this->easingPath;
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $load = parent::attach($attach);

    if (!empty($attach['lazy'])) {
      $load['library'][] = 'blazy/loading';
    }

    // Load optional easing library.
    if ($this->getEasingPath()) {
      $load['library'][] = 'slick/slick.easing';
    }

    $load['library'][] = 'slick/slick.load';

    foreach (['colorbox', 'mousewheel'] as $component) {
      if (!empty($attach[$component])) {
        $load['library'][] = 'slick/slick.' . $component;
      }
    }

    if (!empty($attach['skin'])) {
      $this->attachSkin($load, $attach);
    }

    // Attach default JS settings to allow responsive displays have a lookup,
    // excluding wasted/trouble options, e.g.: PHP string vs JS object.
    $excludes = explode(' ', 'mobileFirst appendArrows appendDots asNavFor prevArrow nextArrow respondTo');
    $excludes = array_combine($excludes, $excludes);
    $load['drupalSettings']['slick'] = array_diff_key(Slick::defaultSettings(), $excludes);

    $this->moduleHandler->alter('slick_attach', $load, $attach);
    return $load;
  }

  /**
   * Provides skins only if required.
   */
  public function attachSkin(array &$load, $attach = []) {
    if ($this->configLoad('slick_css', 'slick.settings')) {
      $load['library'][] = 'slick/slick.css';
    }

    if ($this->configLoad('module_css', 'slick.settings')) {
      $load['library'][] = 'slick/slick.theme';
    }

    if (!empty($attach['thumbnail_effect'])) {
      $load['library'][] = 'slick/slick.thumbnail.' . $attach['thumbnail_effect'];
    }

    if (!empty($attach['down_arrow'])) {
      $load['library'][] = 'slick/slick.arrow.down';
    }

    foreach (self::getConstantSkins() as $group) {
      $skin = $group == 'main' ? $attach['skin'] : (isset($attach['skin_' . $group]) ? $attach['skin_' . $group] : '');
      if (!empty($skin)) {
        $skins = $this->getSkinsByGroup($group);
        $provider = isset($skins[$skin]['provider']) ? $skins[$skin]['provider'] : 'slick';
        $load['library'][] = 'slick/' . $provider . '.' . $group . '.' . $skin;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function slick(array $build = []) {
    foreach (SlickDefault::themeProperties() as $key) {
      $build[$key] = isset($build[$key]) ? $build[$key] : [];
    }

    return empty($build['items']) ? [] : [
      '#theme'      => 'slick',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlick']],
    ];
  }

  /**
   * Prepare attributes for the known module features, not necessarily users'.
   */
  public function prepareAttributes(array $build = []) {
    $settings = $build['settings'];
    $attributes = isset($build['attributes']) ? $build['attributes'] : [];
    $classes = [];

    if ($settings['display'] == 'main') {
      // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
      if ($settings['view_name'] && $settings['current_view_mode']) {
        $classes[] = 'view--' . str_replace('_', '-', $settings['view_name']);
        $classes[] = 'view--' . str_replace('_', '-', $settings['view_name'] . '--' . $settings['current_view_mode']);
      }

      // Blazy can still lazyload an unslick.
      if ($settings['lazy'] == 'blazy' || !empty($settings['blazy'])) {
        $attributes['data-blazy'] = empty($settings['blazy_data']) ? '' : Json::encode($settings['blazy_data']);
      }

      // Provide a context for lightbox, or multimedia galleries, save for grid.
      if (!empty($settings['media_switch']) && empty($settings['grid'])) {
        $switch = str_replace('_', '-', $settings['media_switch']);
        $attributes['data-' . $switch . '-gallery'] = TRUE;
      }
    }

    if ($classes) {
      foreach ($classes as $class) {
        $attributes['class'][] = 'slick--' . $class;
      }
    }

    return $attributes;
  }

  /**
   * Builds the Slick instance as a structured array ready for ::renderer().
   */
  public function preRenderSlick(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    $settings = &$build['settings'];
    $settings += SlickDefault::htmlSettings();

    // Adds helper class if thumbnail on dots hover provided.
    if (!empty($settings['thumbnail_effect']) && (!empty($settings['thumbnail_style']) || !empty($settings['thumbnail']))) {
      $dots_class[] = 'slick-dots--thumbnail-' . $settings['thumbnail_effect'];
    }

    // Adds dots skin modifier class if provided.
    if (!empty($settings['skin_dots'])) {
      $dots_class[] = 'slick-dots--' . str_replace('_', '-', $settings['skin_dots']);
    }

    if (isset($dots_class) && !empty($build['optionset'])) {
      $dots_class[] = $build['optionset']->getSetting('dotsClass') ?: 'slick-dots';
      $js['dotsClass'] = implode(" ", $dots_class);
    }

    // Overrides common options to re-use an optionset.
    if ($settings['display'] == 'main') {
      if (!empty($settings['override'])) {
        foreach ($settings['overridables'] as $key => $override) {
          $js[$key] = empty($override) ? FALSE : TRUE;
        }
      }

      // Build the Slick grid if provided.
      if (!empty($settings['grid']) && !empty($settings['visible_items'])) {
        $build['items'] = $this->buildGrid($build['items'], $settings);
      }
    }

    $build['attributes'] = $this->prepareAttributes($build);
    $build['options'] = isset($js) ? array_merge($build['options'], $js) : $build['options'];

    $this->moduleHandler->alter('slick_optionset', $build['optionset'], $settings);

    foreach (SlickDefault::themeProperties() as $key) {
      $element["#$key"] = $build[$key];
    }

    unset($build);
    return $element;
  }

  /**
   * Returns items as a grid display.
   */
  public function buildGrid(array $items = [], array &$settings = []) {
    $grids = [];

    // Enforces unslick with less items.
    if (empty($settings['unslick']) && !empty($settings['count'])) {
      $settings['unslick'] = $settings['count'] < $settings['visible_items'];
    }

    // Display all items if unslick is enforced for plain grid to lightbox.
    // Or when the total is less than visible_items.
    if (!empty($settings['unslick'])) {
      $settings['display']      = 'main';
      $settings['current_item'] = 'grid';
      $settings['count']        = 2;

      $grids[0] = $this->buildGridItem($items, 0, $settings);
    }
    else {
      // Otherwise do chunks to have a grid carousel, and also update count.
      $preserve_keys     = !empty($settings['preserve_keys']);
      $grid_items        = array_chunk($items, $settings['visible_items'], $preserve_keys);
      $settings['count'] = count($grid_items);

      foreach ($grid_items as $delta => $grid_item) {
        $grids[] = $this->buildGridItem($grid_item, $delta, $settings);
      }
    }
    return $grids;
  }

  /**
   * Returns items as a grid item display.
   */
  public function buildGridItem(array $items, $delta, array $settings = []) {
    $slide = [
      '#theme'    => 'slick_grid',
      '#items'    => $items,
      '#delta'    => $delta,
      '#settings' => $settings,
    ];
    return ['slide' => $slide, 'settings' => $settings];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build = []) {
    foreach (SlickDefault::themeProperties() as $key) {
      $build[$key] = isset($build[$key]) ? $build[$key] : [];
    }

    $settings       = &$build['settings'];
    $id             = isset($settings['id']) ? $settings['id'] : '';
    $settings['id'] = Blazy::getHtmlId('slick', $id);

    $slick = [
      '#theme'      => 'slick_wrapper',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlickWrapper']],
      // Satisfy CTools blocks as per 2017/04/06: 2804165.
      'items'       => [],
    ];

    $this->moduleHandler->alter('slick_build', $slick, $settings);
    return empty($build['items']) ? [] : $slick;
  }

  /**
   * {@inheritdoc}
   */
  public function preRenderSlickWrapper($element) {
    $build = $element['#build'];
    unset($element['#build']);

    // One slick_theme() to serve multiple displays: main, overlay, thumbnail.
    $settings = array_merge(SlickDefault::htmlSettings(), $build['settings']);
    $id       = $settings['id'];
    $thumb_id = $id . '-thumbnail';
    $options  = $build['options'];
    $switch   = $settings['media_switch'];
    $thumbs   = isset($build['thumb']) ? $build['thumb'] : [];

    // Prevents unused thumb going through the main display.
    unset($build['thumb']);

    // Supports programmatic options defined within skin definitions to allow
    // addition of options with other libraries integrated with Slick without
    // modifying optionset such as for Zoom, Reflection, Slicebox, Transit, etc.
    if (!empty($settings['skin']) && $skins = $this->getSkinsByGroup('main')) {
      if (isset($skins[$settings['skin']]['options'])) {
        $options = array_merge($options, $skins[$settings['skin']]['options']);
      }
    }

    // Additional settings.
    $build['optionset']   = $build['optionset'] ?: Slick::loadWithFallback($settings['optionset']);
    $settings['count']    = empty($settings['count']) ? count($build['items']) : $settings['count'];
    $settings['id']       = $id;
    $settings['nav']      = $settings['nav'] ?: (!empty($settings['optionset_thumbnail']) && isset($build['items'][1]));
    $settings['navpos']   = $settings['nav'] && !empty($settings['thumbnail_position']);
    $settings['vertical'] = $build['optionset']->getSetting('vertical');
    $mousewheel           = $build['optionset']->getSetting('mouseWheel');

    if ($settings['nav']) {
      $options['asNavFor']     = "#{$thumb_id}-slider";
      $optionset_thumbnail     = Slick::loadWithFallback($settings['optionset_thumbnail']);
      $mousewheel              = $optionset_thumbnail->getSetting('mouseWheel');
      $settings['vertical_tn'] = $optionset_thumbnail->getSetting('vertical');
    }
    else {
      // Pass extra attributes such as those from Commerce product variations to
      // theme_slick() since we have no asNavFor wrapper here.
      if (isset($element['#attributes'])) {
        $build['attributes'] = empty($build['attributes']) ? $element['#attributes'] : NestedArray::mergeDeep($build['attributes'], $element['#attributes']);
      }
    }

    // Attach libraries.
    if ($switch && $switch != 'content') {
      $settings[$switch] = empty($settings[$switch]) ? $switch : $settings[$switch];
    }

    // Supports Blazy multi-breakpoint or lightbox images if provided.
    // Cases: Blazy within Views gallery, or references without direct image.
    if (!empty($settings['check_blazy']) && !empty($settings['first_image'])) {
      $this->isBlazy($settings, $settings['first_image']);
    }

    $settings['mousewheel'] = $mousewheel;
    $settings['down_arrow'] = $build['optionset']->getSetting('downArrow');
    $settings['lazy']       = empty($settings['lazy']) ? $build['optionset']->getSetting('lazyLoad') : $settings['lazy'];
    $settings['blazy']      = empty($settings['blazy']) ? $settings['lazy'] == 'blazy' : $settings['blazy'];
    $attachments            = $this->attach($settings);
    $build['options']       = $options;
    $build['settings']      = $settings;

    // Build the Slick wrapper elements.
    $element['#settings'] = $settings;
    $element['#attached'] = empty($build['attached']) ? $attachments : NestedArray::mergeDeep($build['attached'], $attachments);

    // Build the main Slick.
    $slick[0] = $this->slick($build);

    // Build the thumbnail Slick.
    if ($settings['nav'] && $thumbs) {
      foreach (['items', 'options', 'settings'] as $key) {
        $build[$key] = isset($thumbs[$key]) ? $thumbs[$key] : [];
      }

      $settings                     = array_merge($settings, $build['settings']);
      $settings['optionset']        = $settings['optionset_thumbnail'];
      $settings['skin']             = isset($settings['skin_thumbnail']) ? $settings['skin_thumbnail'] : '';
      $settings['display']          = 'thumbnail';
      $build['optionset']           = $optionset_thumbnail;
      $build['settings']            = $settings;
      $build['options']['asNavFor'] = "#{$id}-slider";

      $slick[1] = $this->slick($build);
    }

    // Reverse slicks if thumbnail position is provided to get CSS float work.
    if ($settings['navpos']) {
      $slick = array_reverse($slick);
    }

    // Collect the slick instances.
    $element['#items'] = $slick;
    $element['#cache'] = $this->getCacheMetadata($build);

    unset($build);
    return $element;
  }

}
