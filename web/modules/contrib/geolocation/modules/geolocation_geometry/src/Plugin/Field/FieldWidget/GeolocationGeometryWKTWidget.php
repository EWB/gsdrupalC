<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'geolocation_wkt' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_geometry_wkt",
 *   label = @Translation("Geolocation Geometry WKT"),
 *   field_types = {
 *     "geolocation_geometry"
 *   }
 * )
 */
class GeolocationGeometryWKTWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $description_link = Link::fromTextAndUrl($this->t('WKT data'), Url::fromUri('//en.wikipedia.org/wiki/Well-known_text', ['attributes' => ['target' => '_blank']]))->toString();

    $element['wkt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('WKT / Well Known Text'),
      '#default_value' => isset($items[$delta]->wkt) ? $items[$delta]->wkt : NULL,
      '#empty_value' => '',
      '#description' => $this->t('Please enter valid %wikipedia.', ['%wikipedia' => $description_link]),
      '#required' => $element['#required'],
    ];

    return $element;
  }

}
