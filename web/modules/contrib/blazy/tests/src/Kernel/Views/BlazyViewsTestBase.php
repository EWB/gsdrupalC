<?php

namespace Drupal\Tests\blazy\Kernel\Views;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\Tests\blazy\Traits\BlazyKernelTestTrait;

/**
 * Defines base class for Blazy Views integration.
 */
abstract class BlazyViewsTestBase extends ViewsKernelTestBase {

  use BlazyKernelTestTrait;

  /**
   * Set to TRUE to strict check all configuration saved.
   *
   * This is not crucial as this affects views.view.., not Blazy stuffs.
   *
   * @var bool
   * @todo remove once fixed for: views.view.test_blazy_entity.
   * @see \Drupal\Core\Config\Development\ConfigSchemaChecker
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'breakpoint',
    'responsive_image',
    'filter',
    'link',
    'node',
    'text',
    'options',
    // @todo 'entity_test',
    'views',
    'blazy',
    'blazy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->setUpVariables();
    $this->setUpKernelInstall();
    $this->setUpKernelManager();
    $this->setUpRealImage();
  }

}
