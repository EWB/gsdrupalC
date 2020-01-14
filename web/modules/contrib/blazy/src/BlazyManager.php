<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\Cache;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase {

  /**
   * Returns the enforced content, or image using theme_blazy().
   *
   * @param array $build
   *   The array containing: item, content, settings, or optional captions.
   *
   * @return array
   *   The alterable and renderable array of enforced content, or theme_blazy().
   */
  public function getBlazy(array $build = []) {
    /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    $item = $build['item'] = isset($build['item']) ? $build['item'] : NULL;
    $settings = &$build['settings'];
    $settings += BlazyDefault::itemSettings();

    // Respects content not handled by theme_blazy(), but passed through.
    if (empty($build['content'])) {
      $image = empty($settings['uri']) ? [] : [
        '#theme'       => 'blazy',
        '#delta'       => $settings['delta'],
        '#item'        => $settings['entity_type_id'] == 'user' ? $item : [],
        '#image_style' => $settings['image_style'],
        '#build'       => $build,
        '#pre_render'  => [[$this, 'preRenderImage']],
      ];
    }
    else {
      $image = $build['content'];
    }

    $this->moduleHandler->alter('blazy', $image, $settings);
    return $image;
  }

  /**
   * Builds the Blazy image as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The pre-rendered element.
   *
   * @return array
   *   The renderable array of pre-rendered element.
   */
  public function preRenderImage(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Prepare the main image.
    $this->prepareImage($element, $build);

    // Fetch the newly modified settings.
    $settings = $element['#settings'];

    if (!empty($settings['media_switch'])) {
      if ($settings['media_switch'] == 'content' && !empty($settings['content_url'])) {
        $element['#url'] = $settings['content_url'];
      }
      elseif (!empty($settings['lightbox'])) {
        BlazyLightbox::build($element);
      }
    }

    return $element;
  }

  /**
   * Prepares the Blazy image as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The renderable array being modified.
   * @param array $build
   *   The array of information containing the required Image or File item
   *   object, settings, optional container attributes.
   */
  protected function prepareImage(array &$element, array $build) {
    $item = $build['item'];
    $image = [];
    $settings = $build['settings'];
    $settings['_api'] = TRUE;
    $pathinfo = pathinfo($settings['uri']);
    $settings['extension'] = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
    $settings['ratio'] = empty($settings['ratio']) ? '' : str_replace(':', '', $settings['ratio']);
    $settings['use_media'] = $settings['embed_url'] && in_array($settings['type'], ['audio', 'video']);

    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $build[$key] = isset($build[$key]) ? $build[$key] : [];
    }

    // Blazy has these 3 attributes, yet provides optional ones far below.
    // Sanitize potential user-defined attributes such as from BlazyFilter.
    // Skip attributes via $item, or by module, as they are not user-defined.
    $attributes = isset($build['attributes']) ? $build['attributes'] : [];
    $url_attributes = $build['url_attributes'];
    $item_attributes = empty($build['item_attributes']) ? [] : Blazy::sanitize($build['item_attributes']);

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    if ($item && isset($item->_attributes)) {
      $item_attributes += $item->_attributes;
      unset($item->_attributes);
    }

    // Prepare image URL and its dimensions.
    Blazy::buildUrlAndDimensions($settings, $item);

    // Responsive image integration.
    if (!empty($settings['resimage']) && $settings['extension'] != 'svg') {
      $responsive_image_style = $this->entityLoad($settings['responsive_image_style'], 'responsive_image_style');
      if (!empty($responsive_image_style)) {
        $settings['responsive_image_style_id'] = $responsive_image_style->id();

        Blazy::buildResponsiveImage($image, $settings);
        $element['#cache']['tags'] = $this->getResponsiveImageCacheTags($responsive_image_style);
      }
    }

    // Regular image with custom responsive breakpoints.
    if (empty($settings['responsive_image_style_id'])) {
      // Aspect ratio to fix layout reflow with lazyloaded images responsively.
      // This is outside 'lazy' to allow non-lazyloaded iframes use this too.
      if ($settings['ratio']) {
        Blazy::buildAspectRatio($attributes, $settings);
      }

      if (!empty($settings['lazy'])) {
        // Attach data attributes to either IMG tag, or DIV container.
        if (!empty($settings['background'])) {
          Blazy::buildBreakpointAttributes($attributes, $settings);
          $attributes['class'][] = 'media--background b-bg';
        }
        else {
          Blazy::buildBreakpointAttributes($item_attributes, $settings);
        }

        // Multi-breakpoint aspect ratio only applies if lazyloaded.
        // These may be set once at formatter level, or per breakpoint above.
        if (!empty($settings['blazy_data']['dimensions'])) {
          $attributes['data-dimensions'] = Json::encode($settings['blazy_data']['dimensions']);
        }
      }

      if (empty($settings['_no_cache'])) {
        $file_tags = isset($settings['file_tags']) ? $settings['file_tags'] : [];
        $settings['cache_tags'] = empty($settings['cache_tags']) ? $file_tags : Cache::mergeTags($settings['cache_tags'], $file_tags);

        $element['#cache']['max-age'] = -1;
        foreach (['contexts', 'keys', 'tags'] as $key) {
          if (!empty($settings['cache_' . $key])) {
            $element['#cache'][$key] = $settings['cache_' . $key];
          }
        }
      }
    }

    // With CSS background, IMG may be empty, add thumbnail to the container.
    // Supports unique thumbnail different from main image, such as logo for
    // thumbnail and main image for company profile.
    if (!empty($settings['thumbnail_uri'])) {
      $attributes['data-thumb'] = Blazy::transformRelative($settings['thumbnail_uri']);
    }
    elseif (!empty($settings['thumbnail_style'])) {
      $style = $this->entityLoad($settings['thumbnail_style'], 'image_style');
      $attributes['data-thumb'] = Blazy::transformRelative($settings['uri'], $style);
    }

    // Provides extra attributes as needed, excluding url, item, done above.
    // Was planned to replace sub-module item markups if similarity is found for
    // theme_gridstack_box(), theme_slick_slide(), etc. Likely for Blazy 3.x+.
    foreach (['caption', 'media', 'wrapper'] as $key) {
      $element["#$key" . '_attributes'] = empty($build[$key . '_attributes']) ? [] : Blazy::sanitize($build[$key . '_attributes']);
    }

    // Provides captions, if so configured.
    $captions = empty($build['captions']) ? [] : $this->buildCaption($build['captions'], $settings);
    if ($captions) {
      $element['#caption_attributes']['class'][] = $settings['item_id'] . '__caption';
    }

    // Pass elements to theme_blazy().
    $element['#attributes']      = $attributes;
    $element['#captions']        = $captions;
    $element['#item']            = $item;
    $element['#item_attributes'] = $item_attributes;
    $element['#url_attributes']  = $url_attributes;
    $element['#settings']        = $settings;
    $element['#image']           = $image;
  }

  /**
   * Build captions for both old image, or media entity.
   */
  public function buildCaption(array $captions, array $settings) {
    $content = [];
    foreach ($captions as $key => $caption_content) {
      if ($caption_content) {
        $content[$key]['content'] = $caption_content;
        $content[$key]['tag'] = strpos($key, 'title') !== FALSE ? 'h2' : 'div';
        $class = $key == 'alt' ? 'description' : str_replace('field_', '', $key);
        $content[$key]['attributes'] = new Attribute();
        $content[$key]['attributes']->addClass($settings['item_id'] . '__caption--' . str_replace('_', '-', $class));
      }
    }

    return $content ? ['inline' => $content] : [];
  }

  /**
   * Returns the contents using theme_field(), or theme_item_list().
   *
   * @param array $build
   *   The array containing: settings, children elements, or optional items.
   *
   * @return array
   *   The alterable and renderable array of contents.
   */
  public function build(array $build = []) {
    $build['settings'] += BlazyDefault::htmlSettings();
    $settings = $build['settings'];
    $settings['_grid'] = isset($settings['_grid']) ? $settings['_grid'] : (!empty($settings['style']) && !empty($settings['grid']));

    $this->moduleHandler->alter('blazy_build_settings', $build['settings']);

    // If not a grid, pass the items as regular index children to theme_field().
    // This #pre_render doesn't work if called from Views results.
    if (empty($settings['_grid'])) {
      $settings = $this->prepareBuild($build);
      $build['#blazy'] = $settings;
      $build['#attached'] = $this->attach($settings);
    }
    else {
      $build = [
        '#build'      => $build,
        '#pre_render' => [[$this, 'preRenderBuild']],
      ];
    }

    $this->moduleHandler->alter('blazy_build', $build, $settings);
    return $build;
  }

  /**
   * Builds the Blazy outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Checks if we got some signaled attributes.
    $commerce = isset($element['#ajax_replace_class']);
    $attributes = isset($element['#attributes']) ? $element['#attributes'] : [];
    $attributes = isset($element['#theme_wrappers'], $element['#theme_wrappers']['container']['#attributes']) ? $element['#theme_wrappers']['container']['#attributes'] : $attributes;
    $cache = $this->getCacheMetadata($build);
    $settings = $this->prepareBuild($build);

    // Take over elements for a grid display as this is all we need, learned
    // from the issues such as: #2945524, or product variations.
    // We'll selectively pass or work out $attributes far below.
    $element = BlazyGrid::build($build, $settings);
    $element['#attached'] = $this->attach($settings);
    $element['#cache'] = $cache;

    if ($attributes) {
      // Signals other modules if they want to use it.
      // Cannot merge it into BlazyGrid (wrapper_)attributes, done as grid.
      // Use case: Product variations, best served by ElevateZoom Plus.
      if ($commerce) {
        $element['#container_attributes'] = $attributes;
      }
      else {
        // Use case: VIS, can be blended with UL element safely down here.
        $element['#attributes'] = NestedArray::mergeDeep($element['#attributes'], $attributes);
      }
    }

    return $element;
  }

  /**
   * Prepares Blazy outputs, extract items, and return updated $settings.
   */
  public function prepareBuild(array &$build) {
    // If children are stored within items, reset.
    // Blazy comes late to the party after sub-modules decided what they want.
    $settings = isset($build['settings']) ? $build['settings'] : [];
    $build = isset($build['items']) ? $build['items'] : $build;

    // Supports Blazy multi-breakpoint images if provided, updates $settings.
    // Cases: Blazy within Views gallery, or references without direct image.
    if (!empty($settings['first_image']) && !empty($settings['check_blazy'])) {

      // Views may flatten out the array, bail out.
      // What we do here is extract the formatter settings from the first found
      // image and pass its settings to this container so that Blazy Grid which
      // lacks of settings may know if it should load/ display a lightbox, etc.
      // Lightbox gallery should work without `Use field template` checked.
      if (is_array($settings['first_image'])) {
        $this->isBlazy($settings, $settings['first_image']);
      }
    }

    unset($build['items'], $build['settings']);
    return $settings;
  }

  /**
   * Returns the Responsive image cache tags.
   *
   * @param object $responsive
   *   The responsive image style entity.
   *
   * @return array
   *   The responsive image cache tags, or empty array.
   */
  public function getResponsiveImageCacheTags($responsive) {
    $cache_tags = [];
    $image_styles_to_load = [];
    if ($responsive) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive->getCacheTags());
      $image_styles_to_load = $responsive->getImageStyleIds();
    }

    $image_styles = $this->entityLoadMultiple('image_style', $image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }
    return $cache_tags;
  }

  /**
   * Returns the entity view, if available.
   *
   * @deprecated self::getEntityView is deprecated in blazy:8.x-2.0 and will be
   *   removed from blazy:9.x-1.x. Use BlazyEntity::getEntityView() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    return FALSE;
  }

  /**
   * Returns the enforced content, or image using theme_blazy().
   *
   * FYI, most Blazy codes were originally Slick's, PHP, CSS and JS.
   * It was poorly named self::getImage() while Blazy may also contain Media
   * video with iframe element. Probably getMedia() is cool, but let's stick to
   * self::getBlazy() as Blazy also works without Image nor Media video, such as
   * with just a DIV element for CSS background.
   *
   * @deprecated in blazy:8.x-2.0 and will be removed from blazy:9.x-1.x. Use
   *   self::getBlazy() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getImage(array $build = []) {
    return $this->getBlazy($build);
  }

}
