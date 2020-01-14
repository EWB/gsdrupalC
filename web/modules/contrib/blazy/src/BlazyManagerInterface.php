<?php

namespace Drupal\blazy;

/**
 * Defines re-usable services and functions for blazy plugins.
 */
interface BlazyManagerInterface {

  /**
   * Gets the supported lightboxes.
   *
   * @return array
   *   The supported lightboxes.
   */
  public function getLightboxes();

  /**
   * Sets the lightboxes.
   *
   * @param string $lightbox
   *   The lightbox name, expected to be the module name.
   */
  public function setLightboxes($lightbox);

  /**
   * Cleans up empty, or not so empty, breakpoints.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function cleanUpBreakpoints(array &$settings = []);

  /**
   * Checks if an image style contains crop effect.
   *
   * @param string $style
   *   The image style to check for.
   *
   * @return object|bool
   *   Returns the image style instance if it contains crop effect, else FALSE.
   */
  public function isCrop($style);

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * The supported plugins can add [data-blazy] attribute into its container
   * containing $settings['blazy_data'] converted into [data-blazy] JSON.
   * This allows Blazy Grid, or other Views styles, lacking of UI, to have
   * additional settings extracted from the first Blazy formatter found.
   * Such as media switch/ lightbox. This way the container can add relevant
   * attributes to its container, etc. Also applies to entity references where
   * Blazy is not the main formatter, instead embedded as part of the parent's.
   *
   * @param array $settings
   *   The settings being modified.
   * @param array $item
   *   The first item containing settings or item keys.
   *
   * @see \Drupal\blazy\BlazyManager::prepareBuild()
   * @see \Drupal\blazy\Dejavu\BlazyEntityBase::buildElements()
   */
  public function isBlazy(array &$settings, array $item = []);

  /**
   * Builds breakpoints suitable for top-level [data-blazy] wrapper attributes.
   *
   * The hustle is because we need to define dimensions once, if applicable, and
   * let all images inherit. Each breakpoint image may be cropped, or scaled
   * without a crop. To set dimensions once requires all breakpoint images
   * uniformly cropped. But that is not always the case.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item.
   */
  public function buildDataBlazy(array &$settings, $item = NULL);

}
