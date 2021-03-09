<?php

namespace Drupal\better_formats\Tests;

use Drupal\Tests\taxonomy\Functional\TermTest;

/**
 * Copy of TermTest.
 *
 * @group better_formats
 */
class BetterFormatsTermTest extends TermTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['better_formats'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
  }
}
