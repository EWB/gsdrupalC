<?php

namespace Drupal\block_blacklist;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service to remove blocks from a list.
 */
class Blacklist extends ServiceProviderBase {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Block Blacklist match settings.
   *
   * @var string
   */
  protected $match;

  /**
   * Block Blacklist prefix settings.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Block Blacklist regex settings.
   *
   * @var string
   */
  protected $regex;

  /**
   * Block Blacklist validity.
   *
   * @var bool
   */
  protected $hasSettings;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Configure variables based on configuration settings.
   */
  public function setUp($settings) {
    $this->match = !empty($settings) ? trim($settings['match']) : '';
    $this->prefix = !empty($settings) ? trim($settings['prefix']) : '';
    $this->regex = !empty($settings) ? trim($settings['regex']) : '';
    $this->hasSettings = !empty($this->match . $this->prefix . $this->regex);
  }

  /**
   * Determine if there are any settings to adjust for.
   */
  public function hasSettings() {
    return $this->hasSettings;
  }

  /**
   * Determines whether the given block plugin is allowed or blacklisted.
   *
   * @param string $plugin_id
   *   The block plugin ID to check.
   *
   * @return bool
   *   TRUE if the block is allowed, FALSE otherwise.
   */
  public function blockIsAllowed($plugin_id) {
    static
      $block_is_blacklisted,
      $blocks_in_use = [];

    if (!$this->hasSettings) {
      return TRUE;
    }

    // See which blocks are in use in Layout builder. We must not remove any
    // Layout Builder blocks that are already in use.
    if (!isset($block_is_blacklisted)) {
      foreach ($this
        ->entityTypeManager
        ->getStorage('entity_view_display')
        ->loadMultiple() as $entity_view_display) {

        // Look for block plugins in each Layout Builder section.
        /** @var \Drupal\layout_builder\Section $section */
        foreach ($entity_view_display->getThirdPartySetting(
          'layout_builder', 'sections', []) as $section) {

          /** @var \Drupal\layout_builder\SectionComponent $component */
          foreach ($section->getComponents() as $component) {
            $blocks_in_use[] = $component->getPluginId();
          }
        }
      }

      // Define a helper function.
      $block_is_blacklisted = function ($plugin_id) {
        $list = explode("\n", $this->prefix);
        foreach ($list as $prefix) {
          $prefix = trim($prefix);
          if (!empty($prefix) && strpos($plugin_id, "$prefix:") === 0) {
            return TRUE;
          }
        }
        $list = explode("\n", $this->match);
        foreach ($list as $match) {
          $match = trim($match);
          if (!empty($match) && $plugin_id == $match) {
            return TRUE;
          }
        }
        $list = explode("\n", $this->regex);
        foreach ($list as $regex) {
          $regex = trim($regex);
          if (!empty($regex) && preg_match($regex, $plugin_id, $parts)) {
            return TRUE;
          }
        }
      };
    }

    // Allow the block if it is not in the blacklist, or if it's already in use.
    return in_array($plugin_id, $blocks_in_use)
      || !$block_is_blacklisted($plugin_id);
  }

}
