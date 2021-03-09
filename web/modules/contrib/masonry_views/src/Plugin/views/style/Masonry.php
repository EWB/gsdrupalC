<?php

/**
 * @file
 * Contains \Drupal\masonry_views\Plugin\views\style\Masonry.
 *
 * Sponsored by: www.freelance-drupal.com
 */

namespace Drupal\masonry_views\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render each item in a Masonry Layout.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "masonry",
 *   title = @Translation("Masonry"),
 *   help = @Translation("Display the results in a Masonry layout."),
 *   theme = "views_view_masonry",
 *   display_types = {"normal"}
 * )
 */
class Masonry extends StylePluginBase {

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Does the style plugin support grouping of rows.
   *
   * @var bool
   */
  protected $usesGrouping = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Get default options from Masonry.
    $default_options = \Drupal::service('masonry.service')
      ->getMasonryDefaultOptions();

    // Set default values for Masonry.
    foreach ($default_options as $option => $default_value) {
      $options[$option] = [
        'default' => $default_value,
      ];
      if (is_int($default_value)) {
        $options[$option]['bool'] = TRUE;
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Add Masonry options to views form.
    $form['masonry'] = [
      '#type' => 'details',
      '#title' => $this->t('Masonry'),
      '#open' => TRUE,
    ];
    if (\Drupal::service('masonry.service')->isMasonryInstalled()) {
      $form += \Drupal::service('masonry.service')
        ->buildSettingsForm($this->options);

      // Display each option within the Masonry fieldset.
      foreach (\Drupal::service('masonry.service')
                 ->getMasonryDefaultOptions() as $option => $default_value) {
        $form[$option]['#fieldset'] = 'masonry';
      }

      // Views doesn't use FAPI states, so set dependencies instead.
      $form['masonry_animated']['#dependency'] = [
        'edit-style-options-masonry-resizable' => [1],
      ];
      $form['masonry_animation_duration']['#dependency'] = [
        'edit-style-options-masonry-animated' => [1],
      ];
    }
    else {
      // Disable Masonry as plugin is not installed.
      $form['masonry_disabled'] = [
        '#markup' => $this->t('These options have been disabled as the jQuery Masonry plugin is not installed.'),
        '#fieldset' => 'masonry',
      ];
    }
  }
}
