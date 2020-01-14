<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\Dejavu\BlazyVideoBase;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\BlazyFormatterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Blazy Video' to get VEF videos.
 *
 * @todo remove prior to full release. This means Slick Video which depends
 * on VEF is deprecated for main Slick at Blazy 8.2.x with core Media only.
 *
 * @deprecated BlazyVideoFormatter is deprecated in blazy:8.x-2.0 and will be
 *   removed from blazy:9.x-1.x. Use
 *   \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead.
 * @see https://www.drupal.org/node/3103018
 */
class BlazyVideoFormatter extends BlazyVideoBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterTrait;
  use BlazyVideoTrait;

  /**
   * Constructs a BlazyFormatter object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, BlazyFormatterManager $formatter, BlazyOEmbed $blazy_oembed) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formatter = $this->blazyManager = $formatter;
    $this->blazyOembed = $blazy_oembed;
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
      $container->get('blazy.formatter.manager'),
      $container->get('blazy.oembed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build = [];

    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return $build;
    }

    // Collects specific settings to this formatter.
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items);

    // Build the elements.
    $this->buildElements($build, $items);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * Build the blazy elements.
   */
  public function buildElements(array &$build, $items) {
    $settings = $build['settings'];

    foreach ($items as $delta => $item) {
      $settings['input_url'] = strip_tags($item->value);
      $settings['delta'] = $delta;
      if (empty($settings['input_url'])) {
        continue;
      }

      $this->blazyOembed->build($settings);

      $box = ['item' => $item, 'settings' => $settings];

      // Image with responsive image, lazyLoad, and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($box);
      unset($box);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    return [
      'fieldable_form' => TRUE,
      'multimedia'     => TRUE,
      'view_mode'      => $this->viewMode,
    ] + parent::getScopedFormElements();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getType() === 'video_embed_field';
  }

}
