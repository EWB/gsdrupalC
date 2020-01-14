<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatterBase;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickManagerInterface;
use Drupal\slick\SlickDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for slick image and file ER formatters.
 */
abstract class SlickFileFormatterBase extends BlazyFileFormatterBase {

  /**
   * Constructs a SlickFileFormatterBase instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ImageFactory $image_factory, SlickFormatterInterface $formatter, SlickManagerInterface $manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $image_factory, $formatter);
    $this->formatter = $formatter;
    $this->manager   = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('image.factory'),
      $container->get('slick.formatter'),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::imageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return [];
    }

    // Collects specific settings to this formatter.
    $settings = $this->buildSettings();
    $settings['langcode'] = $langcode;
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items, $files);

    // Build the elements.
    $this->buildElements($build, $files);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $files);

    return $this->manager()->build($build);
  }

  /**
   * Build the slick carousel elements.
   */
  public function buildElements(array &$build, $files) {
    $settings   = &$build['settings'];
    $item_id    = $settings['item_id'];
    $tn_caption = empty($settings['thumbnail_caption']) ? NULL : $settings['thumbnail_caption'];

    foreach ($files as $delta => $file) {
      $settings['delta'] = $delta;
      $settings['type'] = 'image';

      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $file->_referringItem;

      $settings['file_tags'] = $file->getCacheTags();
      $settings['uri']       = $file->getFileUri();

      $element = ['item' => $item, 'settings' => $settings];

      // If imported Drupal\blazy\Dejavu\BlazyVideoTrait.
      $this->buildElement($element, $file);
      $settings = $element['settings'];

      // Image with responsive image, lazyLoad, and lightbox supports.
      $element[$item_id] = $this->formatter->getBlazy($element);

      if (!empty($settings['caption'])) {
        foreach ($settings['caption'] as $caption) {
          $element['caption'][$caption] = empty($element['item']->{$caption}) ? [] : ['#markup' => Xss::filterAdmin($element['item']->{$caption})];
        }
      }

      // Build individual slick item.
      $build['items'][$delta] = $element;

      // Build individual slick thumbnail.
      if (!empty($settings['nav'])) {
        $thumb = ['settings' => $settings];

        // Thumbnail usages: asNavFor pagers, dot, arrows, photobox thumbnails.
        $thumb[$item_id] = empty($settings['thumbnail_style']) ? [] : $this->formatter->getThumbnail($settings, $element['item']);
        $thumb['caption'] = empty($element['item']->{$tn_caption}) ? [] : ['#markup' => Xss::filterAdmin($element['item']->{$tn_caption})];

        $build['thumb']['items'][$delta] = $thumb;
        unset($thumb);
      }

      unset($element);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    return [
      'namespace'       => 'slick',
      'nav'             => TRUE,
      'thumb_captions'  => ['title' => $this->t('Title'), 'alt' => $this->t('Alt')],
      'thumb_positions' => TRUE,
    ] + parent::getScopedFormElements();
  }

}
