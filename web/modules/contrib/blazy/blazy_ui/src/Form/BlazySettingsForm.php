<?php

namespace Drupal\blazy_ui\Form;

use Drupal\Core\Url;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines blazy admin settings form.
 */
class BlazySettingsForm extends ConfigFormBase {

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   Discovers available asset libraries in Drupal.
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
    return 'blazy_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['blazy.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('blazy.settings');

    $form['admin_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Admin CSS'),
      '#default_value' => $config->get('admin_css'),
      '#description'   => $this->t('Uncheck to disable blazy related admin compact form styling, only if not compatible with your admin theme.'),
    ];

    $form['responsive_image'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Support Responsive image'),
      '#default_value' => $config->get('responsive_image'),
      '#description'   => $this->t('Check to support lazyloading for the core Responsive image module. Be sure to use blazy-related formatters.'),
    ];

    $form['unbreakpoints'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Disable custom breakpoints'),
      '#default_value' => $config->get('unbreakpoints'),
      '#description'   => $this->t('Check to permanently disable custom breakpoints which is always disabled when choosing a Responsive image. Only reasonable if consistently using core Responsive image. Note: multi-breakpoint CSS background image will then be disabled, as well.'),
    ];

    $form['one_pixel'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Responsive image 1px placeholder'),
      '#default_value' => $config->get('one_pixel'),
      '#description'   => $this->t('By default a 1px Data URI image is the placeholder for lazyloaded Responsive image. Useful to perform a lot better. Uncheck to disable, and use Drupal-managed smallest/fallback image style instead. Be sure to add proper dimensions or at least min-height/min-width via CSS accordingly to avoid layout reflow since Aspect ratio is not supported with Responsive image yet. Disabling this will result in downloading fallback image as well for non-PICTURE element (double downloads).'),
    ];

    $form['placeholder'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Placeholder'),
      '#default_value' => $config->get('placeholder'),
      '#description'   => $this->t('Overrides global 1px placeholder. Can be URL, e.g.: https://mysite.com/blank.gif. Only useful if continuously using Views rewrite results, see <a href=":url">#2908861</a>. Alternatively use <code>hook_blazy_settings_alter()</code> for more fine-grained control. Leave it empty to use default Data URI to avoid extra HTTP requests. If you have 100 images on a page, you will save 100 extra HTTP requests by leaving it empty.', [':url' => 'https://drupal.org/node/2908861']),
    ];

    $form['blazy'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      '#title'       => $this->t('Blazy settings'),
      '#description' => $this->t('The following settings are related to Blazy library.'),
    ];

    $form['blazy']['loadInvisible'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Load invisible'),
      '#default_value' => $config->get('blazy.loadInvisible'),
      '#description'   => $this->t('Check if you want to load invisible (hidden) elements.'),
    ];

    $form['blazy']['offset'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Offset'),
      '#default_value' => $config->get('blazy.offset'),
      '#description'   => $this->t("The offset controls how early you want the elements to be loaded before they're visible. Default is <strong>100</strong>, so 100px before an element is visible it'll start loading."),
      '#field_suffix'  => 'px',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['blazy']['saveViewportOffsetDelay'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Save viewport offset delay'),
      '#default_value' => $config->get('blazy.saveViewportOffsetDelay'),
      '#description'   => $this->t('Delay for how often it should call the saveViewportOffset function on resize. Default is <strong>50</strong>ms.'),
      '#field_suffix'  => 'ms',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['blazy']['validateDelay'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Set validate delay'),
      '#default_value' => $config->get('blazy.validateDelay'),
      '#description'   => $this->t('Delay for how often it should call the validate function on scroll/resize. Default is <strong>25</strong>ms.'),
      '#field_suffix'  => 'ms',
      '#maxlength'     => 5,
      '#size'          => 10,
    ];

    $form['io'] = [
      '#type'        => 'details',
      '#tree'        => TRUE,
      '#open'        => TRUE,
      '#title'       => $this->t('Intersection Observer API (IO) settings (<b>Experimental!</b>)'),
      '#description' => $this->t('The following settings are related to <a href=":url">IntersectionObserver API</a>.', [':url' => 'https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API']),
    ];

    $form['io']['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable IO API'),
      '#default_value' => $config->get('io.enabled'),
      '#description'   => $this->t('Check if you want to use IO API for modern browsers, and Blazy for oldies.'),
    ];

    $form['io']['unblazy'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Unload bLazy'),
      '#default_value' => $config->get('io.unblazy'),
      '#description'   => $this->t("Check if you are happy with IO. This will not load the original bLazy library, no fallback. Watch out for JS errors at browser consoles, and uncheck if any, or unsure. Blazy is just ~1KB gzip. Clear caches!"),
    ];

    $form['io']['rootMargin'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('rootMargin'),
      '#default_value' => $config->get('io.rootMargin') ?: '0px',
      '#description'   => $this->t("Margin around the root. Can have values similar to the CSS margin property, e.g. <code>10px 20px 30px 40px</code> (top, right, bottom, left). The values can be percentages. This set of values serves to grow or shrink each side of the root element's bounding box before computing intersections. Defaults to all zeros."),
      '#maxlength'     => 120,
      '#size'          => 20,
    ];

    $form['io']['threshold'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('threshold'),
      '#default_value' => $config->get('io.threshold') ?: '0',
      '#description'   => $this->t("Either a single number or an array of numbers which indicate at what percentage of the target's visibility the observer's callback should be executed. If you only want to detect when visibility passes the 50% mark, you can use a value of 0.5. If you want the callback to run every time visibility passes another 25%, you would specify the array [<code>0, 0.25, 0.5, 0.75, 1</code>] (without brackets). The default is 0 (meaning as soon as even one pixel is visible, the callback will be run). A value of 1.0 means that the threshold isn't considered passed until every pixel is visible."),
      '#maxlength'     => 120,
      '#size'          => 20,
    ];

    $form['io']['disconnect'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Disconnect'),
      '#default_value' => $config->get('io.disconnect'),
      '#description'   => $this->t('Check if you want to disconnect IO once all images loaded. If you keep seeing eternal blue loader while an image should be already loaded, this means it is not working yet in all cases. Just uncheck this.'),
    ];

    // Allows sub-modules to provide its own settings.
    $form['extras'] = [
      '#type'   => 'details',
      '#open'   => FALSE,
      '#tree'   => TRUE,
      '#title'  => $this->t('Extra settings'),
      '#access' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('blazy.settings');
    $config
      ->set('admin_css', $form_state->getValue('admin_css'))
      ->set('responsive_image', $form_state->getValue('responsive_image'))
      ->set('unbreakpoints', $form_state->getValue('unbreakpoints'))
      ->set('one_pixel', $form_state->getValue('one_pixel'))
      ->set('placeholder', $form_state->getValue('placeholder'))
      ->set('blazy.loadInvisible', $form_state->getValue(['blazy', 'loadInvisible']))
      ->set('blazy.offset', $form_state->getValue(['blazy', 'offset']))
      ->set('blazy.saveViewportOffsetDelay', $form_state->getValue(['blazy', 'saveViewportOffsetDelay']))
      ->set('blazy.validateDelay', $form_state->getValue(['blazy', 'validateDelay']))
      ->set('io.enabled', $form_state->getValue(['io', 'enabled']))
      ->set('io.unblazy', $form_state->getValue(['io', 'unblazy']))
      ->set('io.rootMargin', $form_state->getValue(['io', 'rootMargin']))
      ->set('io.threshold', $form_state->getValue(['io', 'threshold']))
      ->set('io.disconnect', $form_state->getValue(['io', 'disconnect']));

    if ($form_state->hasValue('extras')) {
      foreach ($form_state->getValue('extras') as $key => $value) {
        $config->set('extras.' . $key, $value);
      }
    }

    $config->save();

    // Invalidate the library discovery cache to update the responsive image.
    $this->libraryDiscovery->clearCachedDefinitions();
    $this->configFactory->clearStaticCache();

    $this->messenger()->addMessage($this->t('Be sure to <a href=":clear_cache">clear the cache</a> if trouble to see the updated settings.', [':clear_cache' => Url::fromRoute('system.performance_settings')->toString()]));

    parent::submitForm($form, $form_state);
  }

}
