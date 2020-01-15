<?php

namespace Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestriction;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionBase;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EntityViewModeRestriction Plugin.
 *
 * @LayoutBuilderRestriction(
 *   id = "entity_view_mode_restriction",
 *   title = @Translation("Entity View Mode"),
 *   description = @Translation("Restrict blocks/layouts per entity view mode"),
 * )
 */
class EntityViewModeRestriction extends LayoutBuilderRestrictionBase {

  use PluginHelperTrait;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Database connection service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, Connection $connection) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->moduleHandler = $module_handler;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    // Respect restrictions on allowed blocks specified by the section storage.
    if (isset($context['section_storage'])) {
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
        $allowed_blocks = (isset($third_party_settings['allowed_blocks'])) ? $third_party_settings['allowed_blocks'] : [];
      }
      else {
        $allowed_blocks = [];
      }
      // Filter blocks from entity-specific SectionStorage (i.e., UI).
      $content_block_types_by_uuid = $this->getBlockTypeByUuid();
      if (!empty($allowed_blocks)) {
        foreach ($definitions as $delta => $definition) {
          $original_delta = $delta;
          $category = (string) $definition['category'];
          // Custom blocks get special treatment.
          if ($definition['provider'] == 'block_content') {
            // 'Custom block types' are disregarded if 'Custom blocks'
            // restrictions are enabled.
            if (isset($allowed_blocks['Custom blocks'])) {
              $category = 'Custom blocks';
            }
            else {
              $category = 'Custom block types';
              $delta_exploded = explode(':', $delta);
              $uuid = $delta_exploded[1];
              $delta = $content_block_types_by_uuid[$uuid];
            }
          }

          if (in_array($category, array_keys($allowed_blocks))) {
            // This category has restrictions.
            if (!in_array($delta, $allowed_blocks[$category])) {
              // The current block is not in the allowed list for this category.
              unset($definitions[$original_delta]);
            }
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function alterSectionDefinitions(array $definitions, array $context) {
    // Respect restrictions on allowed layouts specified by section storage.
    if (isset($context['section_storage'])) {
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
        $allowed_layouts = (isset($third_party_settings['allowed_layouts'])) ? $third_party_settings['allowed_layouts'] : [];
        // Filter blocks from entity-specific SectionStorage (i.e., UI).
        if (!empty($allowed_layouts)) {
          $definitions = array_intersect_key($definitions, array_flip($allowed_layouts));
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAllowedinContext(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    $has_restrictions = FALSE;

    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');
    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
    $allowed_blocks = (isset($third_party_settings['allowed_blocks'])) ? $third_party_settings['allowed_blocks'] : [];
    $bundle = $this->getValuefromSectionStorage([$section_storage], 'bundle');

    // Get "from" section and layout id. (not needed?)
    $section_from = $section_storage->getSection($delta_from);
    $layout_id_from = $section_from->getLayoutId();

    // Get "to" section and layout id.
    $section_to = $section_storage->getSection($delta_to);
    $layout_id_to = $section_to->getLayoutId();

    // Get block information.
    $component = $section_from->getComponent($block_uuid)->toArray();
    $block_id = $component['configuration']['id'];
    $block_id_parts = explode(':', $block_id);

    // Load the plugin definition.
    if ($definition = $this->blockManager()->getDefinition($block_id)) {
      if (is_string($definition['category'])) {
        $category = $definition['category'];
      }
      else {
        $category = $definition['category']->__tostring();
      }
      if ($category == "Custom") {
        // Rename to match Layout Builder Restrictions naming.
        $category = "Custom blocks";
      }

      // If the block category isn't present, there aren't restrictions.
      if (!isset($allowed_blocks[$category])) {
        $has_restrictions = FALSE;
      }

      if (!empty($allowed_blocks)) {
        // There ARE restrictions. Start as restricted.
        $has_restrictions = TRUE;
        if (!isset($allowed_blocks[$category]) && $category != "Custom blocks") {
          // No restrictions have been placed on this category.
          $has_restrictions = FALSE;
        }
        else {
          // Some type of restriction has been placed.
          foreach ($allowed_blocks[$category] as $item) {
            if ($item == $block_id) {
              $has_restrictions = FALSE;
              break;
            }
          }
        }
        // Edge case: Restrict by block type if no custom block restrictions.
        if ($category == 'Custom blocks' && !isset($allowed_blocks['Custom blocks'])) {
          $has_restrictions = FALSE;
          $content_block_types_by_uuid = $this->getBlockTypeByUuid();
          $block_bundle = $content_block_types_by_uuid[end($block_id_parts)];
          if (!empty($allowed_blocks['Custom block types']) && in_array($block_bundle, $allowed_blocks['Custom block types'])) {
            // There are block type restrictions AND
            // this block type has been whitelisted.
            $has_restrictions = FALSE;
          }
          elseif (isset($allowed_blocks['Custom block types'])) {
            // There are block type restrictions BUT
            // this block type has NOT been whitelisted.
            $has_restrictions = TRUE;
          }
        }
      }
      if ($has_restrictions) {
        return t("There is a restriction on %block placement in the %layout %region region for %type content.", [
          "%block" => $definition['admin_label'],
          "%layout" => $layout_id_to,
          "%region" => $region_to,
          "%type" => $bundle,
        ]);
      }
    }

    // Default: this block is not restricted.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');
    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
    $allowed_blocks = (isset($third_party_settings['allowed_blocks'])) ? $third_party_settings['allowed_blocks'] : [];

    // Check if allowed inline blocks are defined in config.
    if (isset($allowed_blocks['Inline blocks'])) {
      return $allowed_blocks['Inline blocks'];
    }
    // If not, then allow all inline blocks.
    else {
      return $this->getInlineBlockPlugins();
    }
  }

  /**
   * Helper function to retrieve uuid->type keyed block array.
   *
   * @return str[]
   *   A key-value array of uuid-block type.
   */
  private function getBlockTypeByUuid() {
    if ($this->moduleHandler->moduleExists('block_content')) {
      // Pre-load all reusable blocks by UUID to retrieve block type.
      $query = $this->database->select('block_content', 'b')
        ->fields('b', ['uuid', 'type']);
      $results = $query->execute();
      return $results->fetchAllKeyed(0, 1);
    }
    return [];
  }

}
