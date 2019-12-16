<?php

namespace Drupal\block_blacklist\Controller;

use Drupal\block_blacklist\LayoutPluginAlter;

/**
 * Class DefaultController.
 */
class LayoutController extends DefaultController {

  /**
   * {@inheritdoc}
   */
  protected function getCaption() {
    return $this->t('This page lists block IDs available to the Layout Builder for all contexts.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getList() {
    $definitions = parent::getList();

    $settings = $this->configFactory->get('block_blacklist.settings');
    $options = [
      'match' => !empty($settings) ? trim($settings->get('layout_match')) : '',
      'prefix' => !empty($settings) ? trim($settings->get('layout_prefix')) : '',
      'regex' => !empty($settings) ? trim($settings->get('layout_regex')) : '',
    ];
    $this->blacklistService->setUp($options);

    if ($this->blacklistService->hasSettings()) {
      $callback = [$this->blacklistService, 'blockIsAllowed'];
      $definitions = array_filter($definitions, $callback, ARRAY_FILTER_USE_KEY);
    }
    return $definitions;

  }

}
