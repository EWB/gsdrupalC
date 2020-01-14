<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickManagerInterface;
use Drupal\slick\SlickDefault;

/**
 * Plugin implementation of the 'Slick Text' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_text",
 *   label = @Translation("Slick Text"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class SlickTextFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use SlickFormatterTrait;

  /**
   * Constructs a SlickImageFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, SlickFormatterInterface $formatter, SlickManagerInterface $manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $container->get('slick.formatter'),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::baseSettings() + SlickDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return [];
    }

    $settings = $this->buildSettings();

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items);

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $key => $item) {
      $element = [
        '#type'     => 'processed_text',
        '#text'     => $item->value,
        '#format'   => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
      $build['items'][$key] = $element;
      unset($element);
    }

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items);

    return $this->manager()->build($build);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings              = $this->getSettings();
    $settings['plugin_id'] = $this->getPluginId();
    $settings['vanilla']   = TRUE;
    return $settings;
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    return [
      'current_view_mode' => $this->viewMode,
      'no_image_style'    => TRUE,
      'no_layouts'        => TRUE,
      'responsive_image'  => FALSE,
      'style'             => TRUE,
      'plugin_id'         => $this->getPluginId(),
      'settings'          => $this->getSettings(),
    ];
  }

}
