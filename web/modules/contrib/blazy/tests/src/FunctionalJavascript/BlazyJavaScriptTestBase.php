<?php

namespace Drupal\Tests\blazy\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;
use Drupal\Tests\blazy\Traits\BlazyCreationTestTrait;

/**
 * Tests the Blazy JavaScript using PhantomJS, or Chromedriver.
 *
 * @group blazy
 */
abstract class BlazyJavaScriptTestBase extends WebDriverTestBase {

  use BlazyUnitTestTrait;
  use BlazyCreationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'filter',
    'image',
    'node',
    'text',
    'blazy',
    'blazy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setUpVariables();

    $this->fileSystem             = $this->container->get('file_system');
    $this->entityFieldManager     = $this->container->get('entity_field.manager');
    $this->formatterPluginManager = $this->container->get('plugin.manager.field.formatter');
    $this->blazyAdmin             = $this->container->get('blazy.admin');
    $this->blazyManager           = $this->container->get('blazy.manager');
    $this->scriptLoader           = 'blazy';
  }

  /**
   * Test the Blazy element from loading to loaded states.
   */
  public function doTestFormatterDisplay() {
    $data['settings']['blazy'] = TRUE;
    $data['settings']['ratio'] = '';
    $data['settings']['image_style'] = 'thumbnail';

    $this->setUpContentTypeTest($this->bundle);
    $this->setUpFormatterDisplay($this->bundle, $data);
    $this->setUpContentWithItems($this->bundle);
    $session = $this->getSession();
    $image_path = $this->getImagePath(TRUE);

    $this->drupalGet('node/' . $this->entity->id());

    // Ensures Blazy is not loaded on page load.
    // @todo recheck since this appears to be randomly failing since D8.7.
    // Likely the images are not having enough vertical space to be below the
    // fold. This appears to be no issues with BlazyFilter.
    // @todo $this->assertSession()->elementNotExists('css', '.b-loaded');
    // Capture the initial page load moment.
    $this->createScreenshot($image_path . '/' . $this->scriptLoader . '_1_initial.png');
    $this->assertSession()->elementExists('css', '.b-lazy');

    // Trigger Blazy to load images by scrolling down window.
    $session->executeScript('window.scrollTo(0, document.body.scrollHeight);');

    // Capture the loading moment after scrolling down the window.
    $this->createScreenshot($image_path . '/' . $this->scriptLoader . '_2_loading.png');

    // Wait a moment.
    $session->wait(3000);

    // Verifies that one of the images is there once loaded.
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', '.b-loaded'));

    // Capture the loaded moment.
    // The screenshots are at sites/default/files/simpletest/blazy.
    $this->createScreenshot($image_path . '/' . $this->scriptLoader . '_3_loaded.png');
  }

}
