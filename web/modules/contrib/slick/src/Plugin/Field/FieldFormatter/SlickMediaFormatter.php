<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'slick media' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_media",
 *   label = @Translation("Slick Media"),
 *   description = @Translation("Display the referenced entities as a Slick carousel."),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class SlickMediaFormatter extends SlickEntityReferenceFormatterBase {

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entities = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return [];
    }

    // Collects specific settings to this formatter.
    $settings = $this->buildSettings();
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $this->formatter->preBuildElements($build, $items, $entities);

    // Build the elements.
    $this->buildElements($build, $entities, $langcode);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $entities);

    return $this->manager()->build($build);
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings = parent::buildSettings();
    $settings['blazy'] = TRUE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    return [
      'grid_form' => $multiple,
      'style'     => $multiple,
    ] + parent::getScopedFormElements();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storage = $field_definition->getFieldStorageDefinition();
    return $storage->isMultiple() && $storage->getSetting('target_type') === 'media';
  }

}
