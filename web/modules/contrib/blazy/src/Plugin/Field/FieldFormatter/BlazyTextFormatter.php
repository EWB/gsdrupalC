<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\BlazyDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Blazy Grid Text' formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_text",
 *   label = @Translation("Blazy Grid"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class BlazyTextFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use BlazyFormatterTrait;

  /**
   * Constructs a BlazyImageFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, BlazyManagerInterface $formatter) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formatter = $formatter;
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
      $container->get('blazy.formatter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::baseSettings() + BlazyDefault::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return [];
    }

    // Build the settings.
    $settings               = $this->buildSettings();
    $settings['namespace']  = 'blazy';
    $settings['langcode']   = $langcode;
    $settings['_grid']      = TRUE;
    $settings['field_name'] = $items->getFieldDefinition()->getName();

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    $build = ['settings' => $settings];
    foreach ($items as $item) {
      $build[] = [
        '#type'     => 'processed_text',
        '#text'     => $item->value,
        '#format'   => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $this->admin()->buildSettingsForm($element, $this->getScopedFormElements());
    return $element;
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    return [
      'current_view_mode' => $this->viewMode,
      'grid_form'         => TRUE,
      'grid_required'     => TRUE,
      'no_image_style'    => TRUE,
      'no_layouts'        => TRUE,
      'responsive_image'  => FALSE,
      'style'             => TRUE,
      'plugin_id'         => $this->getPluginId(),
      'settings'          => $this->getSettings(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

}
