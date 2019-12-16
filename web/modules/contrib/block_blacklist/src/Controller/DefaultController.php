<?php

namespace Drupal\block_blacklist\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\block_blacklist\Blacklist;

/**
 * Class DefaultController.
 */
class DefaultController extends ControllerBase {

  /**
   * Drupal\Core\Block\BlockManagerInterface.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Drupal\Core\Plugin\Context\LazyContextRepository.
   *
   * @var \Drupal\Core\Plugin\Context\LazyContextRepository
   */
  protected $contextRepository;

  /**
   * The Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Blacklist service.
   *
   * @var \Drupal\block_blacklist\Blacklist
   */
  protected $blacklistService;

  /**
   * Constructs a new DefaultController object.
   *
   * @param Drupal\Core\Block\BlockManagerInterface $block_manager
   *  The Block Manager service.
   * @param Drupal\Core\Plugin\Context\LazyContextRepository $context_repository
   *  The Context Repository service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\block_blacklist\Blacklist $blacklist_service
   *   The Block Blacklist service.
   */
  public function __construct(
    BlockManagerInterface $block_manager,
    LazyContextRepository $context_repository,
    ConfigFactoryInterface $config_factory,
    Blacklist $blacklist_service) {
    $this->blockManager = $block_manager;
    $this->contextRepository = $context_repository;
    $this->configFactory = $config_factory;
    $this->blacklistService = $blacklist_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('context.repository'),
      $container->get('config.factory'),
      $container->get('block_blacklist.blacklist')
    );
  }

  /**
   * List.
   *
   * @return string
   *   Return list of all available block ids after processing.
   */
  public function list() {

    $definitions = $this->getList();
    $header = [
      $this->t('Module'),
      $this->t('Label'),
      $this->t('Block ID'),
    ];
    $rows = [];
    foreach ($definitions as $id => $definition) {
      $rows[] = [
        $definition['provider'],
        $definition['admin_label'],
        $id,
      ];
    }
    return [
      '#type' => 'table',
      '#caption' => $this->getCaption(),
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Provide a caption for the table.
   *
   * @return array
   *   Formatted text for the caption.
   */
  protected function getCaption() {
    return $this->t('This page lists all system-wide block IDs for all contexts.');
  }

  /**
   * Create the list of block IDs.
   *
   * @return array
   *   An array of block definitions.
   */
  protected function getList() {
    $contexts = $this->contextRepository->getAvailableContexts();
    return $this->blockManager->getDefinitionsForContexts($contexts);
  }
}
