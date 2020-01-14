<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * A Trait common for slick formatters.
 */
trait SlickFormatterTrait {

  /**
   * The slick field formatter manager.
   *
   * @var \Drupal\slick\SlickManagerInterface
   */
  protected $manager;

  /**
   * Returns the slick field formatter service.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * Returns the slick service.
   */
  public function manager() {
    return $this->manager;
  }

  /**
   * Returns the slick admin service shortcut.
   */
  public function admin() {
    return \Drupal::service('slick.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->getSettingsSummary($this->getScopedFormElements());
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

}
