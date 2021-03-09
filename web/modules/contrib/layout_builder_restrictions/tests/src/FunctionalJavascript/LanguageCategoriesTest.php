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
class LanguageCategoriesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'language',
    'locale',
    'block',
    'help',
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
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a node bundle.
    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'configure any layout',
      'create and edit custom blocks',
    ]));
    // Install Norwegian language.
    $edit = [];
    $edit['predefined_langcode'] = 'nb';
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    $edit = [
      'site_default_language' => 'nb',
    ];
    $this->drupalPostForm('admin/config/regional/language', $edit, t('Save configuration'));

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => 1];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Make sure the Norwegian language is prefix free.
    $edit = [
      'prefix[en]' => 'en',
      'prefix[nb]' => '',
    ];
    $this->drupalPostForm('admin/config/regional/language/detection/url', $edit, t('Save configuration'));

    // Make sure one of the strings we know will be translated.
    $locale_storage = $this->container->get('locale.storage');
    $string = NULL;
    $strings = $locale_storage->getStrings([
      'source' => '@entity fields',
    ]);
    if (!empty($strings)) {
      $string = reset($strings);
    }
    else {
      $string = $locale_storage->createString([
        'source' => '@entity fields',
      ])->save();
    }
    $locale_storage->createTranslation([
      'lid' => $string->getId(),
      'language' => 'nb',
      'translation' => '@entity felter',
    ])->save();
    // Add translation for 'Help'.
    $strings = $locale_storage->getStrings([
      'source' => 'Help',
    ]);
    if (!empty($strings)) {
      $string = reset($strings);
    }
    else {
      $string = $locale_storage->createString([
        'source' => 'Help',
      ])->save();
    }
    $locale_storage->createTranslation([
      'lid' => $string->getId(),
      'language' => 'nb',
      'translation' => 'Hjelp',
    ])->save();
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
    // Initially, the Hjelp block is available.
    $assert_session->linkExists('Hjelp');

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
    // Restrict the Hjelp block.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-help-restriction-whitelisted"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-help-available-blocks-help-block"]');
    $element->click();

    $page->pressButton('Save');

    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    // Establish that the 'body' field is no longer present.
    $assert_session->linkNotExists('Body');
    // Establish that the Hjelp block is still present.
    $assert_session->linkExists('Hjelp');
  }

}
