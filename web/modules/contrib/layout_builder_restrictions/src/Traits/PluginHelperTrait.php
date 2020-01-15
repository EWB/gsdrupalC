<?php

namespace Drupal\layout_builder_restrictions\Traits;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;

/**
 * Methods to help Layout Builder Restrictions plugins.
 */
trait PluginHelperTrait {

  use LayoutBuilderContextTrait;

  /**
   * Gets block definitions appropriate for an entity display.
   *
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   *   The entity display being edited.
   *
   * @return array[]
   *   Keys are category names, and values are arrays of which the keys are
   *   plugin IDs and the values are plugin definitions.
   */
  protected function getBlockDefinitions(LayoutEntityDisplayInterface $display) {

    // Check for 'load' method, which only exists in > 8.7.
    if (method_exists($this->sectionStorageManager(), 'load')) {
      $section_storage = $this->sectionStorageManager()->load('defaults', ['display' => EntityContext::fromEntity($display)]);
    }
    else {
      // BC for < 8.7.
      $section_storage = $this->sectionStorageManager()->loadEmpty('defaults')->setSectionList($display);
    }
    // Do not use the plugin filterer here, but still filter by contexts.
    $definitions = $this->blockManager()->getDefinitions();

    // Create a list of block_content IDs for later filtering.
    $custom_blocks = [];
    foreach ($definitions as $key => $definition) {
      if ($definition['provider'] == 'block_content') {
        $custom_blocks[] = $key;
      }
    }

    // Allow filtering of available blocks by other parts of the system.
    $definitions = $this->contextHandler()->filterPluginDefinitionsByContexts($this->getAvailableContexts($section_storage), $definitions);
    $grouped_definitions = $this->blockManager()->getGroupedDefinitions($definitions);

    // Create a new category of block_content blocks that meet the context.
    foreach ($grouped_definitions as $category => $definitions) {
      foreach ($definitions as $key => $definition) {
        if (in_array($key, $custom_blocks)) {
          $grouped_definitions['Custom blocks'][$key] = $definition;
          // Remove this block_content from its previous category so
          // that it is defined only in one place.
          unset($grouped_definitions[$category][$key]);
        }
      }
    }
    // Do not use the 'Custom' group category: it is now redundant, and
    // it is less accurate than relying on block_content.
    unset($grouped_definitions['Custom']);

    // Generate a list of custom block types under the
    // 'Custom block types' namespace.
    $custom_block_bundles = $this->entityTypeBundleInfo()->getBundleInfo('block_content');
    if ($custom_block_bundles) {
      $grouped_definitions['Custom block types'] = [];
      foreach ($custom_block_bundles as $machine_name => $value) {
        $grouped_definitions['Custom block types'][$machine_name] = [
          'admin_label' => $value['label'],
          'category' => t('Custom block types'),
        ];
      }
    }
    ksort($grouped_definitions);

    return $grouped_definitions;
  }

  /**
   * A helper function to return values derivable from section storage.
   *
   * @param array $section_storage
   *   A section storage object nested in an array.
   *   - \Drupal\layout_builder\SectionStorageInterface, or
   *   - \Drupal\layout_builder\OverridesSectionStorageInterface.
   * @param string $requested_value
   *   The value to be returned.
   *
   * @return mixed
   *   The return value depends on $requested_value parameter:
   *   - contexts (array)
   *   - entity (object)
   *   - view mode (string)
   *   - bundle (string)
   *   - entity_type (string)
   *   - storage (object)
   *   - view_display (object)
   */
  public function getValuefromSectionStorage(array $section_storage, $requested_value) {
    $section_storage = array_shift($section_storage);
    $contexts = $section_storage->getContexts();
    if ($requested_value == 'contexts') {
      return $contexts;
    }
    if ($section_storage instanceof OverridesSectionStorageInterface) {
      $entity = $contexts['entity']->getContextValue();
      $view_mode = $contexts['view_mode']->getContextValue();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
    }
    elseif (isset($contexts['entity']) && $contexts['entity']->getContextValue() instanceof ConfigEntityBase) {
      $entity = $view_display = $contexts['entity']->getContextValue();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
    }
    elseif (get_class($section_storage) == 'Drupal\mini_layouts\Plugin\SectionStorage\MiniLayoutSectionStorage') {
      $view_display = $contexts['display']->getContextValue();
    }
    elseif (isset($contexts['display'])) {
      $entity = $contexts['display']->getContextValue();
      $view_mode = $entity->getMode();
      $bundle = $entity->getTargetBundle();
      $entity_type = $entity->getTargetEntityTypeId();
    }
    elseif (isset($contexts['layout'])) {
      $entity = $contexts['layout']->getContextValue();
      // Layout entities do not define view_modes.
      $view_mode = 'default';
      $bundle = $entity->getTargetBundle();
      $entity_type = $entity->getTargetEntityType();
    }
    switch ($requested_value) {
      case 'entity':
        return $entity;

      case 'view_mode':
        return $view_mode;

      case 'bundle':
        return $bundle;

      case 'entity_type':
        return $entity_type;
    }

    $context = $entity_type . "." . $bundle . "." . $view_mode;
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    if ($requested_value == 'storage') {
      return $storage;
    }
    if (empty($view_display)) {
      $view_display = $storage->load($context);
    }
    if ($requested_value == 'view_display') {
      return $view_display;
    }

    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
    if ($requested_value == 'third_party_settings') {
      return $third_party_settings;
    }

    return NULL;
  }

  /**
   * Gets a list of all plugins available as Inline Blocks.
   *
   * @return array
   *   An array of inline block plugins.
   */
  public function getInlineBlockPlugins() {
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('block_content');
    $inline_blocks = [];
    foreach ($bundles as $machine_name => $bundle) {
      $inline_blocks[] = 'inline_block:' . $machine_name;
    }
    return $inline_blocks;
  }

  /**
   * Gets layout definitions.
   *
   * @return array[]
   *   Keys are layout machine names, and values are layout definitions.
   */
  protected function getLayoutDefinitions() {
    return $this->layoutManager()->getFilteredDefinitions('layout_builder', []);
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return $this->sectionStorageManager ?? \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

  /**
   * Gets the block manager.
   *
   * @return \Drupal\Core\Block\BlockManagerInterface
   *   The block manager.
   */
  private function blockManager() {
    return $this->blockManager ?? \Drupal::service('plugin.manager.block');
  }

  /**
   * Gets the layout plugin manager.
   *
   * @return \Drupal\Core\Layout\LayoutPluginManagerInterface
   *   The layout plugin manager.
   */
  private function layoutManager() {
    return $this->layoutManager ?? \Drupal::service('plugin.manager.core.layout');
  }

  /**
   * Gets the context handler.
   *
   * @return \Drupal\Core\Plugin\Context\ContextHandlerInterface
   *   The context handler.
   */
  private function contextHandler() {
    return $this->contextHandler ?? \Drupal::service('context.handler');
  }

  /**
   * Gets the entity bundle interface.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   An interface for an entity type bundle info.
   */
  private function entityTypeBundleInfo() {
    return $this->entityTypeBundleInfo ?? \Drupal::service('entity_type.bundle.info');
  }

}
