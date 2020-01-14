<?php

namespace Drupal\blazy;

/**
 * Provides common field formatter-related methods: Blazy, Slick.
 */
class BlazyFormatterManager extends BlazyManager {

  /**
   * The first image item found.
   *
   * @var object
   */
  protected $firstItem = NULL;

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private $isDimensionSet;

  /**
   * Modifies the field formatter settings inherited by child elements.
   *
   * @param array $build
   *   The array containing: settings, or potential optionset for extensions.
   * @param object $items
   *   The Drupal\Core\Field\FieldItemListInterface items.
   */
  public function buildSettings(array &$build, $items) {
    $settings       = &$build['settings'];
    $count          = $items->count();
    $field          = $items->getFieldDefinition();
    $entity         = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id      = $entity->id();
    $bundle         = $entity->bundle();
    $field_name     = $field->getName();
    $field_type     = $field->getType();
    $field_clean    = str_replace("field_", '', $field_name);
    $target_type    = $field->getFieldStorageDefinition()->getSetting('target_type');
    $view_mode      = empty($settings['current_view_mode']) ? '_custom' : $settings['current_view_mode'];
    $namespace      = $settings['namespace'] = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $id             = isset($settings['id']) ? $settings['id'] : '';
    $gallery_id     = "{$namespace}-{$entity_type_id}-{$bundle}-{$field_clean}-{$view_mode}";
    $id             = Blazy::getHtmlId("{$gallery_id}-{$entity_id}", $id);
    $switch         = empty($settings['media_switch']) ? '' : $settings['media_switch'];
    $internal_path  = $absolute_path = NULL;

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew() && method_exists($entity, 'hasLinkTemplate')) {
      if ($entity->hasLinkTemplate('canonical')) {
        $url = $entity->toUrl();
        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
    }

    $settings['bundle']         = $bundle;
    $settings['cache_metadata'] = ['keys' => [$id, $count]];
    $settings['content_url']    = $settings['absolute_path'] = $absolute_path;
    $settings['count']          = $count;
    $settings['entity_id']      = $entity_id;
    $settings['entity_type_id'] = $entity_type_id;
    $settings['field_type']     = $field_type;
    $settings['field_name']     = $field_name;
    $settings['gallery_id']     = str_replace('_', '-', $gallery_id . '-' . $switch);
    $settings['id']             = $id;
    $settings['internal_path']  = $internal_path;
    $settings['lightbox']       = ($switch && in_array($switch, $this->getLightboxes())) ? $switch : FALSE;
    $settings['placeholder']    = $this->configLoad('placeholder', 'blazy.settings');
    $settings['resimage']       = function_exists('responsive_image_get_image_dimensions') && $this->configLoad('responsive_image', 'blazy.settings') && !empty($settings['responsive_image_style']);
    $settings['target_type']    = $target_type;

    unset($entity, $field);

    if (!empty($settings['vanilla'])) {
      $settings = array_filter($settings);
      return;
    }

    // Don't bother if using Responsive image.
    $settings['breakpoints'] = isset($settings['breakpoints']) && empty($settings['responsive_image_style']) ? $settings['breakpoints'] : [];
    $settings['caption']     = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['background']  = empty($settings['responsive_image_style']) && !empty($settings['background']);
    $settings['blazy']       = $settings['resimage'] || !empty($settings['blazy']);
    $settings['one_pixel']   = $this->configLoad('one_pixel', 'blazy.settings');

    // Let Blazy handle CSS background as Slick's background is deprecated.
    if ($settings['background']) {
      $settings['blazy'] = TRUE;
    }

    if ($settings['blazy']) {
      $settings['lazy'] = 'blazy';
    }

    // Aspect ratio isn't working with Responsive image, yet.
    // However allows custom work to get going with an enforced.
    $ratio = FALSE;
    if (!empty($settings['ratio'])) {
      $ratio = empty($settings['responsive_image_style']);
      if ($settings['ratio'] == 'enforced' || $settings['background']) {
        $ratio = TRUE;
      }
    }

    // Add the entity to formatter cache tags.
    $settings['cache_tags'][] = $settings['entity_type_id'] . ':' . $settings['entity_id'];
    $settings['ratio'] = $ratio ? $settings['ratio'] : FALSE;
  }

  /**
   * Modifies the field formatter settings inherited by child elements.
   *
   * @param array $build
   *   The array containing: settings, or potential optionset for extensions.
   * @param object $items
   *   The Drupal\Core\Field\FieldItemListInterface items.
   * @param array $entities
   *   The optional entities array, not available for non-entities: text, image.
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    $this->buildSettings($build, $items);
    $settings = &$build['settings'];

    // Pass first item to optimize sizes this time.
    if (isset($items[0]) && $item = $items[0]) {
      $entity = isset($entities[0]) ? $entities[0] : NULL;
      $this->extractFirstItem($settings, $item, $entity);
    }

    // Sets dimensions once, if cropped, to reduce costs with ton of images.
    // This is less expensive than re-defining dimensions per image.
    $this->cleanUpBreakpoints($settings);
    if (!empty($settings['first_uri']) && empty($settings['resimage'])) {
      $this->setDimensionsOnce($settings, $this->firstItem);
    }

    // Allows altering the settings.
    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

  /**
   * Modifies the field formatter settings not inherited by child elements.
   *
   * @param array $build
   *   The array containing: items, settings, or a potential optionset.
   * @param object $items
   *   The Drupal\Core\Field\FieldItemListInterface items.
   * @param array $entities
   *   The optional entities array, not available for non-entities: text, image.
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Rebuild the first item to build colorbox/zoom-like gallery.
    $build['settings']['first_item'] = $this->firstItem;
  }

  /**
   * Extract the first image item to build colorbox/zoom-like gallery.
   *
   * @param array $settings
   *   The $settings array being modified.
   * @param object $item
   *   The Drupal\image\Plugin\Field\FieldType\ImageItem item.
   * @param object $entity
   *   The optional media entity.
   */
  public function extractFirstItem(array &$settings, $item, $entity = NULL) {
    if ($settings['field_type'] == 'image') {
      $this->firstItem = $item;
      $settings['first_uri'] = ($file = $item->entity) && empty($item->uri) ? $file->getFileUri() : $item->uri;
    }
    elseif ($entity && $entity->hasField('thumbnail') && $image = $entity->get('thumbnail')->first()) {
      $this->firstItem = $image;
      $settings['first_uri'] = $image->entity->getFileUri();
    }
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * The implementor should only call this if not using Responsive image style.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $item
   *   The first image item found.
   */
  public function setDimensionsOnce(array &$settings = [], $item = NULL) {
    if (!isset($this->isDimensionSet[md5($settings['first_uri'])])) {
      $dimensions['width']  = $settings['original_width'] = $item && isset($item->width) ? $item->width : NULL;
      $dimensions['height'] = $settings['original_height'] = $item && isset($item->height) ? $item->height : NULL;

      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = $this->isCrop($settings['image_style']))) {
        $style->transformDimensions($dimensions, $settings['first_uri']);

        $settings['height'] = $dimensions['height'];
        $settings['width']  = $dimensions['width'];

        // Informs individual images that dimensions are already set once.
        $settings['_dimensions'] = TRUE;
      }

      // Also sets breakpoint dimensions once, if cropped.
      if (!empty($settings['breakpoints'])) {
        $this->buildDataBlazy($settings, $item);
      }

      $this->isDimensionSet[md5($settings['first_uri'])] = TRUE;
    }
  }

}
