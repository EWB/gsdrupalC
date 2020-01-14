<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\BlazyEntity;
use Drupal\blazy\Dejavu\BlazyEntityReferenceBase;
use Drupal\slick\SlickDefault;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for slick entity reference formatters with field details.
 *
 * @see \Drupal\slick_media\Plugin\Field\FieldFormatter
 * @see \Drupal\slick_paragraphs\Plugin\Field\FieldFormatter
 */
abstract class SlickEntityReferenceFormatterBase extends BlazyEntityReferenceBase implements ContainerFactoryPluginInterface {

  use SlickFormatterTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a SlickMediaFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, ImageFactory $image_factory, BlazyEntity $blazy_entity, SlickFormatterInterface $formatter, SlickManagerInterface $manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->imageFactory = $image_factory;
    $this->blazyEntity = $blazy_entity;
    $this->blazyOembed = $blazy_entity->oembed();
    $this->formatter = $formatter;
    $this->manager = $manager;
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
      $container->get('logger.factory'),
      $container->get('image.factory'),
      $container->get('blazy.entity'),
      $container->get('slick.formatter'),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::extendedSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function buildElementThumbnail(array &$build, $element, $entity, $delta) {
    // @todo move it to Slick as too specific for Slick which has thumbnail.
    // The settings in $element has updated metadata extracted from media.
    $settings = $element['settings'];
    $item_id = $settings['item_id'];
    if (!empty($settings['nav'])) {
      // Thumbnail usages: asNavFor pagers, dot, arrows, photobox thumbnails.
      $element[$item_id] = empty($settings['thumbnail_style']) ? [] : $this->formatter()->getThumbnail($settings, $element['item']);
      $element['caption'] = empty($settings['thumbnail_caption']) ? [] : $this->blazyEntity()->getFieldRenderable($entity, $settings['thumbnail_caption'], $settings['view_mode']);

      $build['thumb']['items'][$delta] = $element;
    }
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings              = $this->getSettings();
    $settings['plugin_id'] = $this->getPluginId();

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $admin       = $this->admin();
    $target_type = $this->getFieldSetting('target_type');
    $views_ui    = $this->getFieldSetting('handler') == 'default';
    $bundles     = $views_ui ? [] : $this->getFieldSetting('handler_settings')['target_bundles'];
    $texts       = ['text', 'text_long', 'string', 'string_long', 'link'];
    $texts       = $admin->getFieldOptions($bundles, $texts, $target_type);

    return [
      'namespace'       => 'slick',
      'thumb_captions'  => $texts,
      'thumb_positions' => TRUE,
      'nav'             => TRUE,
    ] + parent::getScopedFormElements();
  }

}
