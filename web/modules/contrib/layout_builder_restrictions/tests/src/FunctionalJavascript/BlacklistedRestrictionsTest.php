<?php

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Demonstrate that blocks can be individually blacklisted.
 *
 * @group layout_builder_restrictions
 */
class BlacklistedRestrictionsTest extends WebDriverTestBase {

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
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-blacklisted');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-blacklisted');
    // Restrict all 'Content' fields from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-blacklisted"]');
    $element->click();
    // Restrict all Custom block types from options.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-blacklisted"]');
    $element->click();
    $page->pressButton('Save');

    // Establish that the 'body' field is still present,
    // because it has not been explicitly blacklisted.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $assert_session->elementExists('css', '.field--name-body');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    // The "body" field is still present.
    $assert_session->linkExists('Body');
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
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
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-blacklisted');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-blacklisted');

    // Select 'blacklist' inline block types, but do not specify any to be
    // blacklisted.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-inline-blocks-restriction-blacklisted"]');
    $element->click();
    $page->pressButton('Save');

    // Check independent restrctions on Custom block and Inline blocks.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Body');
    $assert_session->linkExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkExists('Alternate Block 1');
    // Inline block types are still allowed.
    $assert_session->linkExists('Create custom block');

    // Blacklist some blocks / block types.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-blacklisted');
    // Blacklist the 'body' field as an option.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Content fields][available_blocks][field_block:node:bundle_with_section_field:body]');
    // Blacklist "basic" Custom block types.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom block types][available_blocks][basic]');
    // Blacklist the "alternate" Inline block type.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Inline blocks][available_blocks][inline_block:alternate]');
    $page->pressButton('Save');

    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $assert_session->elementExists('css', '.field--name-body');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    // The "body" field is restricted.
    $assert_session->linkNotExists('Body');
    // ... but other 'content' fields aren't.
    $assert_session->linkExists('Promoted to front page');
    $assert_session->linkExists('Sticky at top of lists');
    // "Basic" Custom blocks are restricted.
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkNotExists('Basic Block 2');
    // ... but "alternate" Custom blocks are allowed.
    $assert_session->linkExists('Alternate Block 1');
    // Only the basic inline block type is allowed.
    $this->clickLink('Create custom block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkExists('Basic');
    $assert_session->linkNotExists('Alternate');

    // Custom block instances take precedence over custom block type setting.
    $this->drupalGet("$field_ui_prefix/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-blacklisted"]');
    $element->click();
    // Blacklist Basic Block 1.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom blocks][available_blocks][block_content:' . $blocks['Basic Block 1'] . ']');
    // Blacklist Alternate Block 1.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom blocks][available_blocks][block_content:' . $blocks['Alternate Block 1'] . ']');
    $page->pressButton('Save');
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->linkNotExists('Basic Block 1');
    $assert_session->linkExists('Basic Block 2');
    $assert_session->linkNotExists('Alternate Block 1');
  }

}
