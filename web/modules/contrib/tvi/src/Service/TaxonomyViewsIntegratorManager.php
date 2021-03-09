<?php

namespace Drupal\tvi\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default implementation of TaxonomyViewsIntegratorManagerInterface.
 *
 * The manager will inspect the configuration of the passed TermInterface object
 * and determine which view will be injected as the page output.
 *
 * At a later point, it would be great to support adherence to the Views
 * permission settings, there is an outstanding patch and issue for that.
 */
class TaxonomyViewsIntegratorManager implements TaxonomyViewsIntegratorManagerInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * Core config factory service.
   *
   * @var \Drupal\Core\ConfigFactory
   */
  private $config;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $requestStack;

  /**
   * TaxonomyViewsIntegratorManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Core config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request stack.
   */
  public function __construct(ConfigFactory $config, EntityTypeManagerInterface $entity_type_manager, RequestStack $requestStack) {
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $config = $container->get('config.factory');
    $entity_type_manager = $container->get('entity_type.manager');
    $requestStack = $container->get('request_stack');
    return new static($config, $entity_type_manager, $requestStack);
  }

  /**
   * Get the taxonomy view integrator settings for this term entity.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   Active taxonomy term.
   *
   * @return \Drupal\Core\Config\Config
   *   TVI config for the term.
   */
  public function getTermConfigSettings(TermInterface $taxonomy_term) {
    return $this->config->get('tvi.taxonomy_term.' . $taxonomy_term->id());
  }

  /**
   * Get the taxonomy view integrator settings for this terms vocabulary entity.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   Active taxonomy term.
   *
   * @return \Drupal\Core\Config\Config
   *   TVI config for the vocabulary.
   */
  public function getVocabularyConfigSettings(TermInterface $taxonomy_term) {
    return $this->config->get('tvi.taxonomy_vocabulary.' . $taxonomy_term->bundle());
  }

  /**
   * Get the default taxonomy view integrator settings.
   *
   * @return \Drupal\Core\Config\Config
   *   The TVI configuration.
   */
  public function getDefaultConfigSettings() {
    return $this->config->get('tvi.global_settings');
  }

  /**
   * Wrapper method for obtaining parents of a given taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   Starting term.
   *
   * @return array
   *   Parent terms of given term.
   */
  public function getTermParents(TermInterface $taxonomy_term) {
    return $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($taxonomy_term->id());
  }

  /**
   * Return an array of arguments from the URI.
   *
   * It is assumed tha URI will be taxonomy/term/{taxonomy_term}, so anything
   * after that will be returned.
   *
   * @return array
   *   Views arguments.
   */
  public function getRequestUriArguments() {
    return array_slice(explode('/', $this->requestStack->getCurrentRequest()->getRequestUri()), 3);
  }

  /**
   * {@inheritdoc}
   */
  public function getTaxonomyTermView(TermInterface $taxonomy_term) {
    $config = $this->getTermConfigSettings($taxonomy_term);
    $check_vocab = TRUE;
    $check_global = TRUE;

    // If we have no matches, we are going to call the default core term view.
    $view_name = 'taxonomy_term';
    $view_id = 'page_1';
    $view_arguments = [$taxonomy_term->id()];

    if ($config->get('enable_override')) {
      $view_name = $config->get('view');
      $view_id = $config->get('view_display');
    }
    else {
      // Check parent terms for settings.
      // if parent is enabled and has 'children inherit settings' is checked.
      foreach ($this->getTermParents($taxonomy_term) as $parent) {
        // Skip the current term, it is returned by loadAllParents.
        if ($taxonomy_term->id() === $parent->id()) {
          continue;
        }

        $config = $this->getTermConfigSettings($parent);

        if ($config->get('enable_override') && $config->get('inherit_settings')) {
          $view_name = $config->get('view');
          $view_id = $config->get('view_display');
          $check_vocab = FALSE;
          break;
        }
      }

      // Check vocab for settings.
      if ($check_vocab) {
        $config = $this->getVocabularyConfigSettings($taxonomy_term);

        if ($config->get('enable_override') && $config->get('inherit_settings')) {
          $view_name = $config->get('view');
          $view_id = $config->get('view_display');
          $check_global = FALSE;
        }
      }

      // Check for global settings.
      if ($check_global) {
        $config = $this->getDefaultConfigSettings();

        if ($config->get('enable_override')) {
          $view_name = $config->get('view');
          $view_id = $config->get('view_display');
        }
      }
    }

    // If the option to pass all args to views is enabled, pass them on.
    if ($config->get('pass_arguments')) {
      $view_arguments += $this->getRequestUriArguments();
    }

    return Views::getView($view_name)->executeDisplay($view_id, $view_arguments);
  }

}
