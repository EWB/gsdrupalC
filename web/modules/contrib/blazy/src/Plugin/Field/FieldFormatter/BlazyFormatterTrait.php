<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

/**
 * A Trait common for all blazy formatters.
 */
trait BlazyFormatterTrait {

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyFormatterManager
   */
  protected $formatter;

  /**
   * Returns the blazy formatter manager.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings              = $this->getSettings();
    $settings['plugin_id'] = $this->getPluginId();

    return $settings;
  }

  /**
   * Returns the blazy admin service.
   */
  public function admin() {
    return \Drupal::service('blazy.admin.formatter');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->getSettingsSummary($this->getScopedFormElements());
  }

}
