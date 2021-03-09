<?php

namespace Drupal\block_blacklist;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\block_blacklist\Blacklist;

/**
 * Implementation callbacks for plugin alter hooks.
 */
class PluginAlter implements ContainerInjectionInterface {

  /**
   * The Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Blacklist service.
   *
   * @var \Drupal\block_blacklist\Blacklist
   */
  protected $blacklistService;

  /**
   * PluginAlter constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\block_blacklist\Blacklist $blacklist_service
   *   The Block Blacklist service.
   */
  protected function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Blacklist $blacklist_service) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->blacklistService = $blacklist_service;
    $this->setUp();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('block_blacklist.blacklist')
    );
  }

  /**
   * Configure variables based on configuration settings.
   */
  protected function setUp() {
    $settings = $this->configFactory->get('block_blacklist.settings');
    $options = [
      'match' => !empty($settings) ? trim($settings->get('system_match')) : '',
      'prefix' => !empty($settings) ? trim($settings->get('system_prefix')) : '',
      'regex' => !empty($settings) ? trim($settings->get('system_regex')) : '',
    ];
    $this->blacklistService->setUp($options);
  }

  /**
   * Alters block definitions.
   *
   * Speeds up the system performance and the Layout Builder page by removing
   * as many blocks as possible from the system, decreasing the number that it
   * has to parse.
   *
   * @see hook_block_alter()
   * @see hook_plugin_filter_TYPE__CONSUMER_alter()
   */
  public function alterBlocks(&$definitions) {
    if (!$this->blacklistService->hasSettings()) {
      return;
    }
    $callback = [$this->blacklistService, 'blockIsAllowed'];
    $definitions = array_filter($definitions, $callback, ARRAY_FILTER_USE_KEY);
  }

}
