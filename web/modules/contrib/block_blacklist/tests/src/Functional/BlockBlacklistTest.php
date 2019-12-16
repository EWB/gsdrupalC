<?php

namespace Drupal\Tests\block_blacklist\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Block Blacklist module.
 *
 * @group block_blacklist
 */
class BlockBlacklistTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_blacklist',
    'block',
    'layout_discovery',
    'layout_builder',
  ];

  /**
    * A simple user.
    */
  private $user;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() {
    parent::setUp();
    $this->user = $this->DrupalCreateUser([
      'administer site configuration',
      'access block blacklist',
    ]);
  }

  /**
   * Tests that the BlockBlacklist pages can be reached before configuration.
   */
  public function testBlockBlacklistPagesExist() {
    $this->drupalLogin($this->user);
    $pages = [
      'admin/config/block_blacklist/settings',
      'admin/config/block_blacklist/system-list',
      'admin/config/block_blacklist/layout-list',
    ];
    foreach ($pages as $page) {
      $this->drupalGet($page);
      $this->assertResponse(200);
    }
    $this->drupalLogout();
  }

  /**
   * Tests the config form.
   */
  public function testBlockBlacklistConfigForm() {

    // Array of configuration options with the rule to use and a block the rule
    // should hide.
    $replace = [
      'system_match' => [
        'label' => 'System Blacklist Match',
        'rule' => 'help',
        'hides' => 'help',
      ],
      'system_prefix' => [
        'label' => 'System Blacklist Prefix',
        'rule' => 'system_menu_block',
        'hides' => 'system_menu_block:admin',
      ],
      'system_regex' => [
        'label' => 'System Blacklist Regex',
        'rule' => '/local_(.*)_block/',
        'hides' => 'local_tasks_block',
      ],
      'layout_match' => [
        'label' => 'Layout Builder Blacklist Match',
        'rule' => 'page_title_block',
        'hides' => 'page_title_block',
      ],
      'layout_prefix' => [
        'label' => 'Layout Builder Blacklist Prefix',
        'rule' => 'inline_block',
        'hides' => 'inline_block:basic',
      ],
      'layout_regex' => [
        'label' => 'Layout Builder Blacklist Regex',
        'rule' => '/system_(.*)_block/',
        'hides' => 'system_powered_by_block',
      ],
    ];

    // Login.
    $this->drupalLogin($this->user);

    // Access config page.
    $this->drupalGet('admin/config/block_blacklist/settings');
    $this->assertResponse(200);
    // Test the form elements exist and have default values.
    $config = $this->config('block_blacklist.settings');
    
    foreach ($replace as $field => $opt) {
      $this->assertFieldByName(
        $field,
        $config->get('block_blacklist.settings.' . $field),
        $field . ' field has the default value'
      );
    }
    // Test form submission.
    $edit = [];
    foreach ($replace as $field => $opt) {
      $edit[$field] = $opt['rule'];
    }
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));
    $this->assertText(
      'The configuration options have been saved.',
      'The form was saved correctly.'
    );
    // Test the new values are there.
    $this->drupalGet('admin/config/block_blacklist/settings');
    $this->assertSession()->statusCodeEquals(200);
    foreach ($replace as $field => $opt) {
      $string = strtr('//*[@name=":field_name"]', [':field_name' => $field]);
      $elements = $this->xpath($string);
      $value = count($elements) ? $elements[0]->getValue() : NULL;
      $this->assertEquals($value, $opt['rule']);
    }  
    
    // Test block list results.
    $pages = [
      'system' => 'admin/config/block_blacklist/system-list',
      'layout' => 'admin/config/block_blacklist/layout-list',
    ];
    foreach ($pages as $type => $page) {
      $this->drupalGet($page);
      $this->assertSession()->statusCodeEquals(200);
      foreach ($replace as $field => $opt) {
        switch ($field) {
          case $type . '_match':
          case $type . '_prefix':
          case $type . '_regex':
            $this->assertNoText($opt['hides'], $opt['label'] . ' block was not displayed .');
            break;

        }
      }
    }
    $this->drupalLogout();
  }

}
