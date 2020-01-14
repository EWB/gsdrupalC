<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\Xss;

/**
 * Plugin implementation of the `Blazy File` or `Blazy Image` for Blazy only.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 */
class BlazyFormatterBlazy extends BlazyFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build = [];
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $build;
    }

    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';
    $settings['_grid']     = !empty($settings['style']) && !empty($settings['grid']);
    $settings['langcode']  = $langcode;

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items, $files);

    // Build the elements.
    $this->buildElements($build, $files);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $files);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * Build the Blazy elements.
   */
  public function buildElements(array &$build, $files) {
    $settings = $build['settings'];

    foreach ($files as $delta => $file) {
      /* @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $file->_referringItem;

      $settings['delta']     = $delta;
      $settings['file_tags'] = $file->getCacheTags();
      $settings['type']      = 'image';
      $settings['uri']       = $file->getFileUri();
      $box['item']           = $item;
      $box['settings']       = $settings;

      // If imported Drupal\blazy\Dejavu\BlazyVideoTrait.
      $this->buildElement($box, $file);

      // Build caption if so configured.
      if (!empty($settings['caption'])) {
        foreach ($settings['caption'] as $caption) {
          if ($caption_content = $box['item']->{$caption}) {
            $box['captions'][$caption] = ['#markup' => Xss::filterAdmin($caption_content)];
          }
        }
      }

      // Image with grid, responsive image, lazyLoad, and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($box);
      unset($box);
    }
  }

}
