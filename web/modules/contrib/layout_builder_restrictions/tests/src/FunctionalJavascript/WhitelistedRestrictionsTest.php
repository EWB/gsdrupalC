<?php

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Demonstrate that blocks can be individually restricted.
 *
 * @group layout_builder_restrictions
 */
class WhitelistedRestrictionsTest extends WebDriverTestBase {

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
   * Verify that the UI can restrict blocks in Layout Builder settings tray.
   */
  public function testBlockRestriction() {
    // Create 2 custom block types, with 3 block instances.
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ]);
    $bundle->save();
    $bundle = BlockContentType::create([
      'id' => 'alternate',
      'label' => 'Alternate',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    $blocks = [
      'Basic Block 1' => 'basic',
      'Basic Block 2' => 'basic',
      'Alternate Block 1' => 'alternate',
    ];
    foreach ($blocks as $info => $type) {
      $block = BlockContent::create([
        'info' => $info,
        'type' => $type,
        'body' => [
          [
            'value' => 'This is the block content',
            'format' => filter_default_format(),
          ],
        ],
      ]);
      $block->save();
      $blocks[$info] = $block->uuid();
    }

    $this->getSession()->resizeWindow(1200, 2000);
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    // Checking is_enable will show allow_custom.
    $page->checkField('layout[enabled]');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    // Initially, the body field is available.
    $assert_session->linkExists('Body');
    // Initially, custom blocks instances are available.
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
    // Initially, all inline block types are allowed.
    $this->clickLink('Create custom block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');

    // Impose Custom Block type restrictions.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-whitelisted');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-whitelisted');
    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-whitelisted"]');
    $element->click();
    // Restrict all Custom block types from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-whitelisted"]');
    $element->click();
    $page->pressButton('Save');

    // Establish that the 'body' field is no longer present.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    // The "body" field is no longer present.
    $assert_session->elementExists('css', '.field--name-body');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('Body');
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
    // Inline block types are still allowed.
    $this->clickLink('Create custom block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Basic');
    $assert_session->linkExists('Alternate');

    // Impose Inline Block type restrictions.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-whitelisted');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-whitelisted');

    // Restrict all Inline blocks from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-whitelisted"]');
    $element->click();
    $page->pressButton('Save');

    // Check independent restrctions on Custom block and Inline blocks.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('Body');
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
    // Inline block types are not longer allowed.
    $assert_session->linkNotExists('Create custom block');

    // Whitelist some blocks / block types.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-whitelisted');
    // Allow only 'body' field as an option.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Content fields][available_blocks][field_block:node:bundle_with_section_field:body]');
    // Whitelist all "basic" Custom block types.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom block types][available_blocks][basic]');
    // Whitelist "alternate" Inline block type.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Inline blocks][available_blocks][inline_block:alternate]');
    $page->pressButton('Save');

    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    // The "body" field is once again present.
    $assert_session->elementExists('css', '.field--name-body');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Body');
    // ... but other 'content' fields aren't.
    $assert_session->linkNotExists('Promoted to front page');
    $assert_session->linkNotExists('Sticky at top of lists');
    // "Basic" Custom blocks are allowed.
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    // ... but "alternate" Custom blocks are disallowed.
    $assert_session->linkNotExists('Alternate Block 1');
    // Only the basic inline block type is allowed.
    $this->clickLink('Create custom block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('Basic');
    $assert_session->linkExists('Alternate');

    // Custom block instances take precedence over custom block type setting.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-whitelisted"]');
    $element->click();
    // Allow Alternate Block 1.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom blocks][available_blocks][block_content:' . $blocks['Alternate Block 1'] . ']');
    // Allow Basic Block 1.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom blocks][available_blocks][block_content:' . $blocks['Basic Block 1'] . ']');
    $page->pressButton('Save');
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
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
