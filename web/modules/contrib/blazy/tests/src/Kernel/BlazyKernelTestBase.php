<?php

namespace Drupal\Tests\blazy\Kernel;

use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\blazy\Traits\BlazyKernelTestTrait;

/**
 * Defines base class for the Blazy formatter tests.
 */
abstract class BlazyKernelTestBase extends FieldKernelTestBase {

  use BlazyKernelTestTrait;

  /**
   * Set to TRUE to strict check all configuration saved.
   *
   * This is not crucial as this affects responsive_image.., not Blazy stuffs.
   *
   * @var bool
   * @todo remove once fixed for: responsive_image.styles.blazy_picture_test.
   * @see \Drupal\Core\Config\Development\ConfigSchemaChecker
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    // @todo 'entity_test',
    'field',
    'field_ui',
    'file',
    'filter',
    'image',
    'media',
    'breakpoint',
    'responsive_image',
    'node',
    'text',
    'views',
    'blazy',
    'blazy_ui',
    'blazy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setUpVariables();
    $this->setUpKernelInstall();
    $this->setUpKernelManager();
  }

}
