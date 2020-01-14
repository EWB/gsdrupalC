<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\BlazyFormatterManager;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Blazy File' to get VEF/VEM within images/files.
 *
 * @deprecated BlazyFileFormatter is deprecated in blazy:8.x-2.0 and will be
 *   removed from blazy:9.x-1.x. Use
 *   \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead.
 * @see https://www.drupal.org/node/3103018
 */
class BlazyFileFormatter extends BlazyFormatterBlazy {

  use BlazyVideoTrait;

  /**
   * Constructs a BlazyFormatter object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ImageFactory $image_factory, BlazyFormatterManager $formatter, BlazyOEmbed $oembed) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $image_factory, $formatter);
    $this->blazyOembed = $oembed;
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
      $container->get('blazy.formatter.manager'),
      $container->get('blazy.oembed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array &$build, $entity) {
    $settings = $build['settings'];
    /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
    // EntityReferenceItem provides $item->entity Drupal\file\Entity\File.
    if ($item = $this->getImageItem($entity)) {
      $build['item'] = $item['item'];
      $build['settings'] = array_merge($settings, $item['settings']);
    }

    $this->blazyOembed->getMediaItem($build, $entity);
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
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'file';
  }

}
