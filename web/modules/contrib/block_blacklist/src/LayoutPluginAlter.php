<?php

namespace Drupal\block_blacklist;

/**
 * Implementation callbacks for layout builder plugin alter hooks.
 */
class LayoutPluginAlter extends PluginAlter{

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $settings = $this->configFactory->get('block_blacklist.settings');
    $options = [
      'match' => !empty($settings) ? trim($settings->get('layout_match')) : '',
      'prefix' => !empty($settings) ? trim($settings->get('layout_prefix')) : '',
      'regex' => !empty($settings) ? trim($settings->get('layout_regex')) : '',
    ];
    $this->blacklistService->setUp($options);
  }

}
