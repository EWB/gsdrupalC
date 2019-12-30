<?php

namespace Drupal\Tests\layout_builder_styles\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\layout_builder_styles\Entity\LayoutBuilderStyle;

/**
 * Tests the Layout Builder Styles apply as expected.
 *
 * @group layout_builder_styles
 */
class LayoutBuilderStyleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
    'block_content',
    'node',
    'layout_builder_styles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->strictConfigSchema = NULL;
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    // Create two nodes.
    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'name' => 'Bundle with section field',
    ]);

    LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();
  }

  /**
   * Test Layout Builder section styles can be created and applied.
   */
  public function testSectionStyles() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $section_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer site configuration',
    ]));

    // Create styles for section.
    LayoutBuilderStyle::create([
      'id' => 'Foobar',
      'label' => 'Foobar',
      'classes' => 'foo-style-class bar-style-class',
      'type' => 'section',
    ])->save();

    LayoutBuilderStyle::create([
      'id' => 'Foobar2',
      'label' => 'Foobar2',
      'classes' => 'foo2-style-class bar2-style-class',
      'type' => 'section',
    ])->save();

    // Add section to node with new styles.
    $this->drupalGet('node/' . $section_node->id());
    $assert_session->responseNotContains('foo-style-class bar-style-class');
    $assert_session->responseNotContains('foo2-style-class bar2-style-class');
    $page->clickLink('Layout');
    $page->clickLink('Add section');
    $page->clickLink('Two column');
    // Verify that only a single option may be selected.
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option');
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar');
    $page->pressButton('Add section');

    // Confirm section element contains the proper classes.
    $page->pressButton('Save layout');
    $assert_session->responseContains('foo-style-class bar-style-class');
    $assert_session->responseNotContains('foo2-style-class bar2-style-class');

    // Set the configuration to allow multiple styles per block.
    $this->drupalGet('admin/config/content/layout_builder_style/config');
    $page->selectFieldOption('edit-multiselect-multiple', 'multiple');
    $page->selectFieldOption('edit-form-type-multiple-select', 'multiple-select');
    $page->pressButton('Save configuration');

    $this->drupalGet('layout_builder/configure/section/overrides/node.' . $section_node->id() . '/0');
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar', TRUE);
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar2', TRUE);
    $page->pressButton('Update');
    $assert_session->responseContains('foo-style-class bar-style-class');
    $assert_session->responseContains('foo2-style-class bar2-style-class');

  }

  /**
   * Test Layout Builder block styles can be created and applied.
   */
  public function testBlockStyles() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $block_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer site configuration',
    ]));

    // Create styles for blocks.
    LayoutBuilderStyle::create([
      'id' => 'Foobar',
      'label' => 'Foobar',
      'classes' => 'foo-style-class bar-style-class',
      'type' => 'component',
    ])->save();

    LayoutBuilderStyle::create([
      'id' => 'Foobar2',
      'label' => 'Foobar2',
      'classes' => 'foo2-style-class bar2-style-class',
      'type' => 'component',
    ])->save();

    // Add block to node with new style.
    $this->drupalGet('node/' . $block_node->id());
    $assert_session->responseNotContains('foo-style-class bar-style-class');
    $assert_session->responseNotContains('foo2-style-class bar2-style-class');
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    // Verify that only a single option may be selected.
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option');
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');

    // Confirm block element contains proper classes.
    $assert_session->responseContains('foo-style-class bar-style-class');
    $assert_session->responseNotContains('foo2-style-class bar2-style-class');

    // Set the configuration to allow multiple styles per block.
    $this->drupalGet('admin/config/content/layout_builder_style/config');
    $page->selectFieldOption('edit-multiselect-multiple', 'multiple');
    $page->selectFieldOption('edit-form-type-multiple-select', 'multiple-select');
    $page->pressButton('Save configuration');

    // Change block configuration to have multiple styles.
    $components = Node::load($block_node->id())->get('layout_builder__layout')->getSection(0)->getComponents();
    end($components);
    $uuid = key($components);
    $this->drupalGet('layout_builder/update/block/overrides/node.' . $block_node->id() . '/0/content/' . $uuid);
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar', TRUE);
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar2', TRUE);
    $page->pressButton('Update');
    $page->pressButton('Save layout');

    // Confirm block element contains proper classes.
    $assert_session->responseContains('foo-style-class bar-style-class');
    $assert_session->responseContains('foo2-style-class bar2-style-class');

    // Change block back to first style.
    $this->drupalGet('layout_builder/update/block/overrides/node.' . $block_node->id() . '/0/content/' . $uuid);
    $page->selectFieldOption('edit-layout-builder-style', 'Foobar');
    $page->pressButton('Update');
    $page->pressButton('Save layout');

    // Confirm change to settings now only renders first style.
    $assert_session->responseContains('foo-style-class bar-style-class');
    $assert_session->responseNotContains('foo2-style-class bar2-style-class');

    // Change configuration to use checkboxes element.
    $this->drupalGet('admin/config/content/layout_builder_style/config');
    $page->selectFieldOption('edit-multiselect-multiple', 'multiple');
    $page->selectFieldOption('edit-form-type-checkboxes', 'checkboxes');
    $page->pressButton('Save configuration');

    // Change block to use the second style.
    $this->drupalGet('layout_builder/update/block/overrides/node.' . $block_node->id() . '/0/content/' . $uuid);
    $page->uncheckField('edit-layout-builder-style-foobar', 'Foobar');
    $page->selectFieldOption('edit-layout-builder-style-foobar2', 'Foobar2');
    $page->pressButton('Update');
    $page->pressButton('Save layout');

    // Confirm change to settings now only renders second style.
    $assert_session->responseNotContains('foo-style-class bar-style-class');
    $assert_session->responseContains('foo2-style-class bar2-style-class');

  }

  /**
   * Block type restrictions should apply to inline & reusable blocks.
   */
  public function testBlockRestrictions() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $block_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer site configuration',
      'create and edit custom blocks',
    ]));

    // Create 2 custom block types, with block instances.
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

    // Create block styles for blocks.
    LayoutBuilderStyle::create([
      'id' => 'unrestricted',
      'label' => 'Unrestricted',
      'classes' => 'foo-style-class bar-style-class',
      'type' => 'component',
    ])->save();

    // Restrict the 2nd block style to 'basic' blocks.
    LayoutBuilderStyle::create([
      'id' => 'basic_only',
      'label' => 'Basic only',
      'classes' => 'foo2-style-class bar2-style-class',
      'type' => 'component',
      'block_restrictions' => ['inline_block:basic'],
    ])->save();

    // Restrict the 3rd block style to only the 'Promoted to frontpage' block.
    LayoutBuilderStyle::create([
      'id' => 'promoted_only',
      'label' => 'Promoted only',
      'classes' => 'foo3-style-class bar3-style-class',
      'type' => 'component',
      'block_restrictions' => ['field_block:node:bundle_with_section_field:promote'],
    ])->save();

    // Restrict the 4th block style to 'alternate' or 'promoted'.
    LayoutBuilderStyle::create([
      'id' => 'multi_allow',
      'label' => 'Alternate and promoted',
      'classes' => 'foo4-style-class bar4-style-class',
      'type' => 'component',
      'block_restrictions' => ['inline_block:alternate', 'field_block:node:bundle_with_section_field:promote'],
    ])->save();

    // Set the configuration to allow multiple styles per block.
    $this->drupalGet('/admin/config/content/layout_builder_style/config');
    $page->selectFieldOption('edit-multiselect-multiple', 'multiple');
    $page->selectFieldOption('edit-form-type-multiple-select', 'multiple-select');
    $page->pressButton('Save configuration');

    // Examine which styles are allowed on basic block type.
    $this->drupalGet($block_node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Basic Block 1');
    // Basic block can use "Unrestricted" and "Basic only".
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="basic_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="unrestricted"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="promoted_only"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="multi_allow"]');

    // Examine which styles are allowed on alternate block type.
    $this->drupalGet($block_node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Alternate Block 1');
    // Alternate block can use "Unrestricted" and "Alternate only".
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="basic_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="unrestricted"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="promoted_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="multi_allow"]');

    // Examine which styles are allowed on 'Promoted to front page'.
    $this->drupalGet($block_node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Promoted to front page');
    // Promoted gets "Unrestricted", "Alternate and promoted", & "Promoted".
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="basic_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="unrestricted"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="promoted_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="multi_allow"]');

    // Examine which styles are allowed on inline basic block.
    $this->drupalGet($block_node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Create custom block');
    $page->clickLink('Basic');
    // Basic block can use "Unrestricted" and "Basic only".
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="basic_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="unrestricted"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="promoted_only"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="multi_allow"]');

    // Examine which styles are allowed on inline alternate block.
    $this->drupalGet($block_node->toUrl());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Create custom block');
    $page->clickLink('Alternate');
    // Alternate block can use "Unrestricted" and "Alternate only".
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="basic_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="unrestricted"]');
    $assert_session->elementNotExists('css', 'select#edit-layout-builder-style option[value="promoted_only"]');
    $assert_session->elementExists('css', 'select#edit-layout-builder-style option[value="multi_allow"]');
  }

}
