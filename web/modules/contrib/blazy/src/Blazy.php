<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements BlazyInterface.
 */
class Blazy implements BlazyInterface {

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  private static $blazyId;

  /**
   * Prepares variables for blazy.html.twig templates.
   */
  public static function buildAttributes(array &$variables) {
    $element = $variables['element'];
    foreach (BlazyDefault::themeProperties() as $key) {
      $variables[$key] = isset($element["#$key"]) ? $element["#$key"] : [];
    }

    // Provides optional attributes, see BlazyFilter.
    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $variables[$key] = empty($element["#$key"]) ? [] : new Attribute($element["#$key"]);
    }

    // Provides sensible default html settings to shutup notices when lacking.
    $item      = $variables['item'];
    $settings  = &$variables['settings'];
    $settings += BlazyDefault::itemSettings();

    // Still provides a failsafe for direct theme call with a valid Image item.
    if (empty($settings['uri']) && $item) {
      $settings['uri'] = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
    }

    // Do not proceed if no URI is provided.
    if (empty($settings['uri'])) {
      return;
    }

    // URL and dimensions are built out at BlazyManager::preRenderImage().
    // Still provides a failsafe for direct call to this theme.
    if (empty($settings['_api'])) {
      self::buildUrlAndDimensions($settings, $item);
    }

    // Build regular image if not using responsive image.
    // Image is optional for Video, and Blazy CSS background images.
    if (empty($settings['responsive_image_style_id']) && empty($settings['background'])) {
      self::buildImage($variables);
    }

    // Prepares a media player, and allows a tiny video preview without iframe.
    if ($settings['use_media'] && empty($settings['_noiframe'])) {
      self::buildIframeAttributes($variables);
    }

    // Image is optional for Video, and Blazy CSS background images.
    if ($variables['image']) {
      self::buildImageAttributes($variables);
    }
  }

  /**
   * Modifies variables for responsive image.
   *
   * Responsive images with height and width save a lot of calls to
   * image.factory service for every image and breakpoint in
   * _responsive_image_build_source_attributes(). Very necessary for
   * external file system like Amazon S3.
   */
  public static function buildResponsiveImage(array &$image, array &$settings) {
    $image += [
      '#type' => 'responsive_image',
      '#responsive_image_style_id' => $settings['responsive_image_style_id'],
      '#uri' => $settings['uri'],
      '#width' => $settings['width'],
      '#height' => $settings['height'],
      '#attributes' => [
        'data-responsive-blazy' => $settings['one_pixel'],
        'data-placeholder' => $settings['placeholder'],
      ],
    ];

    // Disable aspect ratio which is not yet supported due to complexity.
    $settings['ratio'] = FALSE;
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   */
  public static function buildImage(array &$variables) {
    $settings = $variables['settings'];
    $attributes = &$variables['item_attributes'];

    // Supports either lazy loaded image, or not.
    $variables['image'] += [
      '#theme' => 'image',
      '#uri' => empty($settings['lazy']) ? $settings['image_url'] : $settings['placeholder'],
    ];

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    if (empty($settings['_sizes']) && !isset($attributes['width']) && $settings['extension'] != 'svg') {
      $attributes['height'] = $settings['height'];
      $attributes['width'] = $settings['width'];
    }

    // BC for calling this theme directly bypassing the API.
    if (!empty($settings['lazy']) && empty($settings['_api'])) {
      self::buildLazyAttributes($attributes, $settings);
    }
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   */
  public static function buildImageAttributes(array &$variables) {
    $item = $variables['item'];
    $image = &$variables['image'];
    $attributes = &$variables['item_attributes'];

    // Respects hand-coded image attributes.
    if ($item) {
      if (!isset($attributes['alt'])) {
        $attributes['alt'] = isset($item->alt) ? $item->alt : NULL;
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $attributes['title'] = $item->title;
      }
    }

    $attributes['class'][] = 'media__image media__element';
    $image['#attributes'] = empty($image['#attributes']) ? $attributes : NestedArray::mergeDeep($image['#attributes'], $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public static function buildIframeAttributes(array &$variables) {
    $settings           = &$variables['settings'];
    $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];
    $settings['player'] = empty($settings['player']) ? (empty($settings['lightbox']) && $settings['media_switch'] != 'content') : $settings['player'];
    $iframe['data-src'] = $settings['embed_url'];
    $iframe['src']      = 'about:blank';
    $iframe['class'][]  = 'b-lazy';

    // Prevents broken iframe when aspect ratio is empty.
    if (empty($settings['ratio']) && !empty($settings['width'])) {
      $iframe['width']  = $settings['width'];
      $iframe['height'] = $settings['height'];
    }

    // Pass iframe attributes to template.
    $variables['iframe_attributes'] = new Attribute($iframe);

    // Iframe is removed on lazyloaded, puts data at non-removable storage.
    $variables['attributes']['data-media'] = Json::encode(['type' => $settings['type'], 'scheme' => $settings['scheme']]);
  }

  /**
   * {@inheritdoc}
   */
  public static function buildLazyAttributes(array &$attributes, array $settings = []) {
    $attributes['class'][] = $settings['lazy_class'];
    $attributes['data-' . $settings['lazy_attribute']] = $settings['image_url'];
  }

  /**
   * {@inheritdoc}
   */
  public static function buildBreakpointAttributes(array &$attributes, array &$settings = []) {
    self::buildLazyAttributes($attributes, $settings);

    // Only provide multi-serving image URLs if breakpoints are provided.
    if (empty($settings['breakpoints'])) {
      return;
    }

    $srcset = $json = [];
    // https://css-tricks.com/sometimes-sizes-is-quite-important/
    // For older iOS devices that don't support w descriptors in srcset, the
    // first source item in the list will be used.
    $settings['breakpoints'] = array_reverse($settings['breakpoints']);
    foreach ($settings['breakpoints'] as $key => $breakpoint) {
      if (!($style = ImageStyle::load($breakpoint['image_style']))) {
        continue;
      }

      // Supports multi-breakpoint aspect ratio with irregular sizes.
      // Yet, only provide individual dimensions if not already set.
      // @see Drupal\blazy\BlazyFormatterManager::setDimensionsOnce().
      $width = self::widthFromDescriptors($breakpoint['width']);
      if ($width && !empty($settings['_breakpoint_ratio']) && empty($settings['blazy_data']['dimensions'])) {
        $dimensions = ['width' => $settings['width'], 'height' => $settings['height']];
        $style->transformDimensions($dimensions, $settings['uri']);
        $json[$width] = round((($dimensions['height'] / $dimensions['width']) * 100), 2);
      }

      $url = self::transformRelative($settings['uri'], $style);
      $settings['breakpoints'][$key]['url'] = $url;

      // Still working with GridStack multi-image-style per item at 2019.
      if (!empty($settings['background'])) {
        $attributes['data-src-' . $key] = $url;
      }
      else {
        $width = trim($breakpoint['width']);
        $width = is_numeric($width) ? $width . 'w' : $width;
        $srcset[] = $url . ' ' . $width;
      }
    }

    if ($srcset) {
      $settings['srcset'] = implode(', ', $srcset);

      $attributes['srcset'] = '';
      $attributes['data-srcset'] = $settings['srcset'];
      $attributes['sizes'] = '100w';

      if (!empty($settings['sizes'])) {
        $attributes['sizes'] = trim($settings['sizes']);
        $settings['_sizes'] = $settings['sizes'];
        unset($attributes['height'], $attributes['width']);
      }
    }

    if ($json) {
      $settings['blazy_data']['dimensions'] = $json;
    }
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   */
  public static function buildUri($image_url) {
    if (!UrlHelper::isExternal($image_url) && $normal_path = UrlHelper::parse($image_url)['path']) {
      $public_path = Settings::get('file_public_path');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        return file_build_uri($rel_path);
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildUrlAndDimensions(array &$settings, $item = NULL) {
    $settings['placeholder'] = empty($settings['placeholder']) ? static::PLACEHOLDER : $settings['placeholder'];

    // BlazyFilter, or image style with crop, may already set these.
    if (empty($settings['width'])) {
      $settings['width'] = $item && isset($item->width) ? $item->width : NULL;
      $settings['height'] = $item && isset($item->height) ? $item->height : NULL;
    }

    // Overrides lazy with blazy for explicit call to reduce another param.
    if (!empty($settings['blazy'])) {
      $settings['lazy'] = 'blazy';
    }

    // Provides image_url expected by lazyload, not URI.
    $uri = $settings['uri'];
    $image_url = self::isValidUri($uri) ? self::transformRelative($uri) : $uri;
    $settings['image_url'] = $settings['image_url'] ?: $image_url;

    // Image style modifier can be multi-style images such as GridStack.
    if (!empty($settings['image_style']) && ($style = ImageStyle::load($settings['image_style']))) {
      $settings['image_url'] = self::transformRelative($uri, $style);
      $settings['cache_tags'] = $style->getCacheTags();

      // Only re-calculate dimensions if not cropped, nor already set.
      if (empty($settings['_dimensions'])) {
        $style->transformDimensions($settings, $uri);
      }
    }

    // Just in case, an attempted kidding gets in the way.
    $use_data_uri = !empty($settings['use_data_uri']) && substr($settings['image_url'], 0, 10) === 'data:image';
    if (!$use_data_uri) {
      $settings['image_url'] = UrlHelper::stripDangerousProtocols($settings['image_url']);
    }
  }

  /**
   * A wrapper for file_url_transform_relative() to pass tests anywhere else.
   */
  public static function transformRelative($uri, $style = NULL) {
    $url = $style ? $style->buildUrl($uri) : file_create_url($uri);
    return file_url_transform_relative($url);
  }

  /**
   * Modifies container attributes with aspect ratio.
   */
  public static function buildAspectRatio(array &$attributes, array &$settings) {
    $attributes['class'][] = 'media--ratio media--ratio--' . $settings['ratio'];

    if ($settings['width'] && in_array($settings['ratio'], ['enforced', 'fluid'])) {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      $padding = $settings['padding_bottom'] ?: round((($settings['height'] / $settings['width']) * 100), 2);
      $attributes['style'] = 'padding-bottom: ' . $padding . '%';

      // Provides hint to breakpoints to work with multi-breakpoint ratio.
      $settings['_breakpoint_ratio'] = $settings['ratio'];

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      $attributes['data-ratio'] = $padding;
    }
  }

  /**
   * Gets the numeric "width" part from a descriptor.
   */
  public static function widthFromDescriptors($descriptor = '') {
    // Dynamic multi-serving aspect ratio with backward compatibility.
    $descriptor = trim($descriptor);
    if (is_numeric($descriptor)) {
      return (int) $descriptor;
    }

    // Cleanup w descriptor to fetch numerical width for JS aspect ratio.
    $width = strpos($descriptor, "w") !== FALSE ? str_replace('w', '', $descriptor) : $descriptor;

    // If both w and x descriptors are provided.
    if (strpos($descriptor, " ") !== FALSE) {
      // If the position is expected: 640w 2x.
      list($width, $px) = array_pad(array_map('trim', explode(" ", $width, 2)), 2, NULL);

      // If the position is reversed: 2x 640w.
      if (is_numeric($px) && strpos($width, "x") !== FALSE) {
        $width = $px;
      }
    }

    return is_numeric($width) ? (int) $width : FALSE;
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function preprocessResponsiveImage(array &$variables) {
    $image = &$variables['img_element'];
    $attributes = &$variables['attributes'];
    $placeholder = empty($attributes['data-placeholder']) ? static::PLACEHOLDER : $attributes['data-placeholder'];

    // Prepare all <picture> [data-srcset] attributes on <source> elements.
    if (!$variables['output_image_tag']) {
      /** @var \Drupal\Core\Template\Attribute $source */
      if (isset($variables['sources']) && is_array($variables['sources'])) {
        foreach ($variables['sources'] as &$source) {
          $srcset = $source['srcset'];
          $srcset_values = $srcset->value();

          $source->setAttribute('data-srcset', $srcset_values);
          $source->removeAttribute('srcset');
        }
      }

      // Fetches the picture element fallback URI, and empty it later, 8.x-3+.
      $fallback_uri = $image['#uri'];

      // Cleans up the no-longer relevant attributes for controlling element.
      unset($attributes['data-srcset'], $image['#attributes']['data-srcset']);
      $image['#srcset'] = '';

      // Prevents invalid IMG tag when one pixel placeholder is disabled.
      $image['#uri'] = $placeholder;
    }
    else {
      $srcset = $attributes['srcset'];
      $srcset_values = $srcset->value();
      $fallback_uri = $image['#uri'];

      $attributes['data-srcset'] = $srcset_values;
      $image['#attributes']['data-srcset'] = $srcset_values;
      $image['#attributes']['srcset'] = '';
    }

    // Blazy needs controlling element to have fallback [data-src], else error.
    $image['#attributes']['data-src'] = $fallback_uri;
    $image['#attributes']['class'][] = 'b-lazy b-responsive';

    // The [data-responsive-blazy] is a flag indicating 1px placeholder.
    // This prevents double-downloading the fallback image, if enabled.
    if (!empty($attributes['data-responsive-blazy'])) {
      $image['#uri'] = $placeholder;
    }

    // Cleans up the no-longer needed flag:
    unset($attributes['data-responsive-blazy'], $image['#attributes']['data-responsive-blazy']);
    unset($attributes['data-placeholder'], $image['#attributes']['data-placeholder']);
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', array $settings = []) {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
      foreach ($settings as $key => $value) {
        // Seems double is ignored, and causes a missing schema, unlike float.
        $type = gettype($value);
        $type = $type == 'double' ? 'float' : $type;
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : $type);

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_', ' ', $key));
        }
      }

      if (isset($mappings['breakpoints'])) {
        foreach (BlazyDefault::getConstantBreakpoints() as $breakpoint) {
          $mappings['breakpoints']['mapping'][$breakpoint]['type'] = 'mapping';
          foreach (['breakpoint', 'width', 'image_style'] as $item) {
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['type']  = 'string';
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['label'] = Unicode::ucfirst(str_replace('_', ' ', $item));
          }
        }
      }
    }
  }

  /**
   * Returns the sanitized attributes common for user-defined ones.
   *
   * When IMG and IFRAME are allowed for untrusted users, trojan horses are
   * welcome. Hence sanitize attributes relevant for BlazyFilter. The rest
   * should be taken care of by HTML filters after Blazy.
   */
  public static function sanitize(array $attributes = []) {
    $clean_attributes = [];
    $tags = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];
    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $clean_attributes[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        // Since Blazy is lazyloading known URLs, sanitize attributes which
        // make no sense to stick around within IMG or IFRAME tags.
        $kid = substr($key, 0, 2) === 'on' || in_array($key, $tags);
        $key = $kid ? 'data-' . $key : $key;
        $clean_attributes[$key] = $kid ? Html::cleanCssIdentifier($value) : Html::escape($value);
      }
    }
    return $clean_attributes;
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = '') {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * This is just a wrapper around
   * Drupal\Core\StreamWrapper\StreamWrapperManager::isValidUri() for Drupal
   * versions >= 8.8, with a fallback to file_valid_uri() for prior Drupal
   * versions.
   *
   * @param string $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   *
   * @todo Remove this once Drupal 8.7 is no longer supported.
   */
  private static function isValidUri($uri) {
    if (version_compare(\Drupal::VERSION, '8.8', '>=')) {
      return \Drupal::service('stream_wrapper_manager')->isValidUri($uri);
    }
    else {
      // Because this code only runs for older Drupal versions, we do not need
      // or want IDEs or the Upgrade Status module warning people about this
      // deprecated code usage. Setting the function name dynamically
      // circumvents those warnings.
      $function = 'file_valid_uri';
      return $function($uri);
    }
  }

}
