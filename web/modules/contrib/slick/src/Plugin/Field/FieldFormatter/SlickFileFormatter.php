<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\Dejavu\BlazyVideoTrait;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Slick File' formatter to get ME within images.
 *
 * This is not 'Slick Media', instead a simple mix of image and optional video.
 *
 * @deprecated for Slick Media (with oEmbed).
 * @todo remove post/ prior to 2.x release.
 */
class SlickFileFormatter extends SlickFileFormatterBase {

  use SlickFormatterTrait;
  use BlazyVideoTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ImageFactory $image_factory, SlickFormatterInterface $formatter, SlickManagerInterface $manager, BlazyOEmbed $oembed) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $image_factory, $formatter, $manager);
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
      $container->get('slick.formatter'),
      $container->get('slick.manager'),
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
  public function buildSettings() {
    return ['blazy' => TRUE] + parent::getSettings();
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
    $storage = $field_definition->getFieldStorageDefinition();
    return $storage->isMultiple() && $storage->getSetting('target_type') === 'file';
  }

}
