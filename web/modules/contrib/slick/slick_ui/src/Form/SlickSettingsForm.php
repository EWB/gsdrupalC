<?php

namespace Drupal\slick_ui\Form;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Slick admin settings form.
 */
class SlickSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Asset\LibraryDiscoveryInterface definition.
   *
   * @var Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LibraryDiscoveryInterface $library_discovery) {
    parent::__construct($config_factory);
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('library.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slick_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['slick.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('slick.settings');

    $form['module_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Slick module slick.theme.css'),
      '#description'   => $this->t('Uncheck to permanently disable the module slick.theme.css, normally included along with skins.'),
      '#default_value' => $config->get('module_css'),
      '#prefix'        => $this->t("Note! Slick doesn't need Slick UI to run. It is always safe to uninstall Slick UI once done with optionsets."),
    ];

    $form['slick_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Slick library slick-theme.css'),
      '#description'   => $this->t('Uncheck to permanently disable the optional slick-theme.css, normally included along with skins.'),
      '#default_value' => $config->get('slick_css'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->configFactory->getEditable('slick.settings')
      ->set('slick_css', $form_state->getValue('slick_css'))
      ->set('module_css', $form_state->getValue('module_css'))
      ->save();

    // Invalidate the library discovery cache to update new assets.
    $this->libraryDiscovery->clearCachedDefinitions();
    $this->configFactory->clearStaticCache();

    parent::submitForm($form, $form_state);
  }

}
