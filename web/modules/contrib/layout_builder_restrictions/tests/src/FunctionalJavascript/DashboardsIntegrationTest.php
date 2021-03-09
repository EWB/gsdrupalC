<?php

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Demonstrate that Layout Builder Restrictions works with Dashboards.
 *
 * @group layout_builder_restrictions
 *
 * @requires dashboards
 */
class DashboardsIntegrationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_restrictions',
    'dashboards',
    'field_ui',
    'node',
  ];

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->strictConfigSchema = NULL;
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'configure any layout',
      'administer dashboards',
    ]));
  }

  /**
   * Verify that Layout Builder Restrictions does not break Dashboards.
   */
  public function testDashboardsWithRestrictionsEnabled() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/dashboards/add');
    $page->fillField('Administrative Label', 'Charlie');
    $this->assertNotEmpty($assert_session->waitForText('Machine name: charlie'));
    $page->pressButton('Save');
    $this->drupalGet('dashboards/charlie/layout');
    $page->clickLink('Add section');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.layout-selection'));
    $page->clickLink('One column');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add section');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
  }

}
