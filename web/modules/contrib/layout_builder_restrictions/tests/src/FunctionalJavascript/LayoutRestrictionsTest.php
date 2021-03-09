<?php

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions
 */
class LayoutRestrictionsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'layout_builder',
    'layout_builder_restrictions',
    'node',
    'field_ui',
    'block_content',
  ];

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a node bundle.
    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'configure any layout',
      'create and edit custom blocks',
    ]));
  }

  /**
   * Verify that the UI can restrict layouts in Layout Builder settings tray.
   */
  public function testLayoutRestriction() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalGet("$field_ui_prefix/display/default");
    // Checking is_enable will show allow_custom.
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    // Baseline: 'One column' & 'Two column' layouts are available.
    $assert_session->elementExists('css', '.field--name-body');
    $this->clickLink('Add section');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('One column');
    $assert_session->linkExists('Two column');

    // Allow only 'Two column' layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layout-restriction-restricted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-layouts-layouts-layout-twocol-section"]');
    $element->click();
    $page->pressButton('Save');

    // Verify 'Two column' is allowed, 'One column' restricted.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add section');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('One column');
    $assert_session->linkExists('Two column');
  }

}
