<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyFormatterManager;
use Drupal\blazy\BlazyEntity;
use Drupal\blazy\Dejavu\BlazyEntityMediaBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick media ER formatters.
 *
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter.
 */
abstract class BlazyMediaFormatterBase extends BlazyEntityMediaBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a BlazyFormatter object.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LoggerChannelFactoryInterface $logger_factory,
    ImageFactory $image_factory,
    BlazyEntity $blazy_entity,
    BlazyFormatterManager $formatter) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->loggerFactory = $logger_factory;
    $this->imageFactory = $image_factory;
    $this->blazyEntity = $blazy_entity;
    $this->formatter = $this->blazyManager = $formatter;
    $this->blazyOembed = $blazy_entity->oembed();
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
      $container->get('blazy.formatter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media';
  }

}
