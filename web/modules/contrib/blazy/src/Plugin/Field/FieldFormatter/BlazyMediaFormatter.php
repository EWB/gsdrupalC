<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin for blazy media formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_media",
 *   label = @Translation("Blazy"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   }
 * )
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 * @see \Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter
 */
class BlazyMediaFormatter extends BlazyMediaFormatterBase {

  /**
   * Returns the overridable blazy field formatter service.
   */
  public function formatter() {
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
    $settings              = $this->buildSettings();
    $settings['blazy']     = TRUE;
    $settings['namespace'] = $settings['item_id'] = $settings['lazy'] = 'blazy';

    // Build the settings.
    $build = ['settings' => $settings];

    // Modifies settings before building elements.
    $entities = array_values($entities);
    $this->formatter->preBuildElements($build, $items, $entities);

    // Build the elements.
    $this->buildElements($build, $entities, $langcode);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $entities);

    // Pass to manager for easy updates to all Blazy formatters.
    return $this->formatter->build($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    return [
      'fieldable_form'  => FALSE,
      'grid_form'       => $multiple,
      'layouts'         => [],
      'settings'        => $this->getSettings(),
      'style'           => $multiple,
      'thumbnail_style' => TRUE,
      'vanilla'         => FALSE,
    ] + parent::getScopedFormElements();
  }

}
