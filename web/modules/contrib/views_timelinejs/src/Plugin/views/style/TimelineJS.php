<?php

namespace Drupal\views_timelinejs\Plugin\views\style;

use DateTime;
use DOMDocument;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views_timelinejs\TimelineJS\Background;
use Drupal\views_timelinejs\TimelineJS\Date;
use Drupal\views_timelinejs\TimelineJS\Era;
use Drupal\views_timelinejs\TimelineJS\Media;
use Drupal\views_timelinejs\TimelineJS\Slide;
use Drupal\views_timelinejs\TimelineJS\Text;
use Drupal\views_timelinejs\TimelineJS\Timeline;
use Drupal\views_timelinejs\TimelineJS\TitleSlide;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render items as TimelineJS3 slides.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "timelinejs",
 *   title = @Translation("TimelineJS"),
 *   help = @Translation("Display the results in a Timeline."),
 *   theme = "views_timelinejs_view_timelinejs",
 *   display_types = {"normal"}
 * )
 */
class TimelineJS extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * The row index of the slide at which the timeline should first be rendered.
   *
   * @var int
   */
  protected $startSlideIndex;

  /**
   * Constructs a TimelineJS object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $module_configuration
   *   The Views TimelineJS module's configuration.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $module_configuration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configuration['library_location'] = $module_configuration->get('library_location');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $module_configuration = $container->get('config.factory')->get('views_timelinejs.settings');
    return new static($configuration, $plugin_id, $plugin_definition, $module_configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['timeline_config'] = [
      'contains' => [
        'width' => ['default' => '100%'],
        'height' => ['default' => '40em'],
        'hash_bookmark' => ['default' => FALSE],
        'scale_factor' => ['default' => 2],
        'timenav_position' => ['default' => 'bottom'],
        'timenav_height' => ['default' => ''],
        'timenav_height_percentage' => ['default' => ''],
        'timenav_mobile_height_percentage' => ['default' => ''],
        'timenav_height_min' => ['default' => ''],
        'start_at_end' => ['default' => FALSE],
        'language' => ['default' => ''],
      ],
    ];
    $options['additional_config'] = [
      'contains' => [
        'font' => ['default' => ''],
        'start_at_current' => ['default' => FALSE],
      ],
    ];
    $options['timeline_fields'] = [
      'contains' => [
        'caption' => ['default' => ''],
        'credit' => ['default' => ''],
        'media' => ['default' => ''],
        'thumbnail' => ['default' => ''],
        'group' => ['default' => ''],
        'start_date' => ['default' => ''],
        'end_date' => ['default' => ''],
        'display_date' => ['default' => ''],
        'text' => ['default' => ''],
        'headline' => ['default' => ''],
        'background' => ['default' => ''],
        'background_color' => ['default' => ''],
        'type' => ['default' => ''],
        'unique_id' => ['default' => ''],
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $initial_labels = ['' => $this->t('- None -')];
    $view_fields_labels = $this->displayHandler->getFieldLabels();
    $view_fields_labels = array_merge($initial_labels, $view_fields_labels);

    // Timeline general configuration.  Values within this fieldset will be
    // passed directly to the TimelineJS settings object.  As a result, form
    // element keys should be given the same ID as TimelineJS settings, e.g.
    // $form['timeline_config']['id_of_timelinejs_option'].  See the list of
    // options at https://timeline.knightlab.com/docs/options.html.
    $form['timeline_config'] = [
      '#type' => 'details',
      '#title' => $this->t('TimelineJS Options'),
      '#description' => $this->t('Each of these settings maps directly to one of the TimelineJS presentation options.  See the <a href="@options-doc">options documentation page</a> for additional information.', ['@options-doc' => 'https://timeline.knightlab.com/docs/options.html']),
      '#open' => TRUE,
    ];
    $form['timeline_config']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline width'),
      '#description' => $this->t('The width of the timeline, e.g. "100%" or "650px".'),
      '#default_value' => $this->options['timeline_config']['width'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline height'),
      '#description' => $this->t('The height of the timeline, e.g. "40em" or "650px".  Percent values are not recommended for the height.'),
      '#default_value' => $this->options['timeline_config']['height'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['hash_bookmark'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add hash bookmarks'),
      '#description' => $this->t('On each slide, a # will be added to the end of the url in the url bar. These urls are bookmarkable, so you can share or return to the same place in the timeline later.'),
      '#default_value' => $this->options['timeline_config']['hash_bookmark'],
    ];
    $form['timeline_config']['scale_factor'] = [
      '#type' => 'number',
      '#title' => $this->t('Scale factor'),
      '#description' => $this->t('How many screen widths wide the timeline should be at first presentation.'),
      '#default_value' => $this->options['timeline_config']['scale_factor'],
      '#min' => 0,
      '#max' => 10,
      '#step' => 0.25,
    ];
    $form['timeline_config']['timenav_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Timeline navigation position'),
      '#options' => [
        'bottom' => $this->t('Bottom'),
        'top' => $this->t('Top'),
      ],
      '#default_value' => $this->options['timeline_config']['timenav_position'],
    ];
    $form['timeline_config']['timenav_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline navigation height'),
      '#description' => $this->t('The height of the timeline navigation, in pixels.  Enter an integer value.'),
      '#default_value' => $this->options['timeline_config']['timenav_height'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['timenav_height_percentage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline navigation height percentage'),
      '#description' => $this->t('The height of the timeline navigation, in percent.  Enter an integer value.  Overridden by the Timeline navigation height setting.'),
      '#default_value' => $this->options['timeline_config']['timenav_height_percentage'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['timenav_mobile_height_percentage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline navigation mobile height percentage'),
      '#description' => $this->t('The height of the timeline navigation on mobile device screens, in percent.  Enter an integer value.'),
      '#default_value' => $this->options['timeline_config']['timenav_mobile_height_percentage'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['timenav_height_min'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeline navigation height minimum'),
      '#description' => $this->t('The minimum height of the timeline navigation, in pixels.  Enter an integer value.'),
      '#default_value' => $this->options['timeline_config']['timenav_height_min'],
      '#size' => 10,
      '#maxlength' => 10,
    ];
    $form['timeline_config']['start_at_end'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start at the end'),
      '#description' => $this->t('Loads the timeline on the last slide.'),
      '#default_value' => $this->options['timeline_config']['start_at_end'],
    ];
    $form['timeline_config']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#description' => $this->t("By default, the timeline will be displayed in the site's current language if it is supported by TimelineJS. Selecting a language in this setting will force the timeline to always be displayed in the chosen language."),
      '#options' => array_merge($initial_labels, _views_timelinejs_list_languages()),
      '#default_value' => $this->options['timeline_config']['language'],
    ];

    // Timeline additional configuration.
    $form['additional_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional Options'),
      '#description' => $this->t('These settings include extra options to control the TimelineJS presentation or options unique to this plugin.'),
      '#open' => TRUE,
    ];
    $form['additional_config']['font'] = [
      '#type' => 'select',
      '#title' => $this->t('Font set'),
      '#description' => $this->t('TimelineJS3 offers several pre-selected font sets.  If a set is selected its CSS file will be downloaded from the CDN.'),
      '#options' => array_merge($initial_labels, _views_timelinejs_list_font_sets()),
      '#default_value' => $this->options['additional_config']['font'],
    ];
    $form['additional_config']['start_at_current'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start at Current'),
      '#description' => $this->t('Loads the timeline on the slide closest to the current time.  Overrides the "Start at the End" setting.'),
      '#default_value' => $this->options['additional_config']['start_at_current'],
    ];

    // Field mapping.
    $form['timeline_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mappings'),
      '#description' => $this->t('Map your Views data fields to TimelineJS slide object properties.'),
      '#open' => TRUE,
    ];
    $form['timeline_fields']['headline'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Headline'),
      '#description' => $this->t('The selected field may contain any text, including HTML markup.'),
      '#default_value' => $this->options['timeline_fields']['headline'],
    ];
    $form['timeline_fields']['text'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Body text'),
      '#description' => $this->t('The selected field may contain any text, including HTML markup.'),
      '#default_value' => $this->options['timeline_fields']['text'],
    ];
    $form['timeline_fields']['start_date'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Start date'),
      '#required' => TRUE,
      '#description' => $this->t('The selected field should contain a string representing a date conforming to a <a href="@php-manual">PHP supported date and time format</a>.  Start dates are required by event slides and eras.  If this mapping is not configured or if the field does not output dates in a valid format, then the slides or eras will not be added to the timeline.', ['@php-manual' => 'http://php.net/manual/en/datetime.formats.php']),
      '#default_value' => $this->options['timeline_fields']['start_date'],
    ];
    $form['timeline_fields']['end_date'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('End date'),
      '#description' => $this->t('The selected field should contain a string representing a date conforming to a <a href="@php-manual">PHP supported date and time format</a>.  End dates are required by eras.  If this mapping is not configured or if the field does not output dates in a valid format, then the eras will not be added to the timeline.', ['@php-manual' => 'http://php.net/manual/en/datetime.formats.php']),
      '#default_value' => $this->options['timeline_fields']['end_date'],
    ];
    $form['timeline_fields']['display_date'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Display date'),
      '#description' => $this->t('The selected field should contain a string.  TimelineJS will display this value instead of the values of the start and end date fields.'),
      '#default_value' => $this->options['timeline_fields']['display_date'],
    ];
    $form['timeline_fields']['background'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Background image'),
      '#description' => $this->t('The selected field should contain a raw URL to an image.  Special handling is included for Image fields because they have no raw URL formatter.'),
      '#default_value' => $this->options['timeline_fields']['background'],
    ];
    $form['timeline_fields']['background_color'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Background color'),
      '#description' => $this->t('The selected field should contain a string representing a CSS color, in hexadecimal (e.g. #0f9bd1) or a valid <a href="@color-keywords">CSS color keyword</a>.', ['@color-keywords' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/color_value#Color_keywords']),
      '#default_value' => $this->options['timeline_fields']['background_color'],
    ];
    $form['timeline_fields']['media'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Media'),
      '#description' => $this->t('The selected field should contain a raw URL to a media resource, an HTML blockquote, or an HTML iframe.  See the <a href="@media-documentation">media types documentation</a> for a list of supported types.  Special handling is included for Image fields because they have no raw URL formatter.', ['@media-documentation' => 'https://timeline.knightlab.com/docs/media-types.html']),
      '#default_value' => $this->options['timeline_fields']['media'],
    ];
    $form['timeline_fields']['credit'] = [
      '#type' => 'select',
      '#title' => $this->t('Media Credit'),
      '#description' => $this->t('The selected field may contain any text, including HTML markup.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['timeline_fields']['credit'],
    ];
    $form['timeline_fields']['caption'] = [
      '#type' => 'select',
      '#title' => $this->t('Media Caption'),
      '#description' => $this->t('The selected field may contain any text, including HTML markup.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['timeline_fields']['caption'],
    ];
    $form['timeline_fields']['thumbnail'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Media thumbnail'),
      '#description' => $this->t('The selected field should contain a raw URL for an image to use in the timenav marker for this event. If omitted, TimelineJS will use an icon based on the type of media.  Special handling is included for Image fields because they have no raw URL formatter.'),
      '#default_value' => $this->options['timeline_fields']['thumbnail'],
    ];
    $form['timeline_fields']['group'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Group'),
      '#description' => $this->t('The selected field may contain any text. If present, TimelineJS will organize events with the same value for group to be in the same row or adjacent rows, separate from events in other groups. The common value for the group will be shown as a label at the left edge of the navigation.'),
      '#default_value' => $this->options['timeline_fields']['group'],
    ];
    $form['timeline_fields']['type'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Type'),
      '#description' => $this->t('Determines the type of timeline entity that is rendered: event, title slide, or era.  This plugin recognizes a limited set of string values to determine the type.  "title" or "timeline_title_slide" will cause a views data row to be rendered as a TimelineJS title slide.  Only one title slide can be created per timeline.  Additional title slides will overwrite previous slides.  "era" or "timeline_era" rows will be rendered as TimelineJS eras.  By default, a row with an empty value or any other input will be rendered as a regular event slide.'),
      '#default_value' => $this->options['timeline_fields']['type'],
    ];
    $form['timeline_fields']['unique_id'] = [
      '#type' => 'select',
      '#options' => $view_fields_labels,
      '#title' => $this->t('Unique ID'),
      '#description' => $this->t('The selected field should contain a string value which is unique among all slides in your timeline, e.g. a node ID. If not specified, TimelineJS will construct an ID based on the headline, but if you later edit your headline, the ID will change. Unique IDs are used when the hash_bookmark option is used.'),
      '#default_value' => $this->options['timeline_fields']['unique_id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Return if the start date field mapping is not configured.
    if (empty($this->options['timeline_fields']['start_date'])) {
      $this->messenger()->addWarning(t('The Start date field mapping must be configured in the TimelineJS format settings before any slides or eras can be rendered.'));
      return;
    }
    $timeline = new Timeline();

    // Render the fields.  If it isn't done now then the row_index will be unset
    // the first time that getField() is called, resulting in an undefined
    // property exception.
    $this->renderFields($this->view->result);

    // Render slide arrays from the views data.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;

      // Determine the type of timeline entity to build.
      $type = 'event';
      if ($this->options['timeline_fields']['type']) {
        $type = $this->getField($row_index, $this->options['timeline_fields']['type']);
      }
      switch ($type) {
        case 'title':
        case 'timeline_title_slide':
          $slide = $this->buildTitleSlide();
          // Ensure the slide was built.
          if (!empty($slide)) {
            $timeline->setTitleSlide($slide);
          }
          break;

        case 'era':
        case 'timeline_era':
          $era = $this->buildEra();
          // Ensure the era was built.
          if (!empty($era)) {
            $timeline->addEra($era);
          }
          break;

        default:
          $slide = $this->buildSlide();
          // Ensure the slide was built.
          if (!empty($slide)) {
            $timeline->addEvent($slide);
          }
      }
    }
    unset($this->view->row_index);

    // Skip theming if the view is being edited or previewed.
    if ($this->view->preview) {
      return '<pre>' . print_r($timeline->buildArray(), 1) . '</pre>';
    }

    // Prepare the options array.
    $this->prepareTimelineOptions();

    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => [
        'timeline_options' => $this->options['timeline_config'],
        'timeline_font' => $this->options['additional_config']['font'],
      ],
      '#rows' => $timeline->buildArray(),
    ];
  }

  /**
   * Builds a timeline slide from the current views data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Slide|null
   *   A slide object or NULL if the start date could not be parsed.
   */
  protected function buildSlide() {
    $start_date = $this->buildDate($this->options['timeline_fields']['start_date']);
    // Return NULL if the slide has no start date.
    if (empty($start_date)) {
      return NULL;
    }
    $end_date = $this->buildDate($this->options['timeline_fields']['end_date']);
    $text = $this->buildText();
    $slide = new Slide($start_date, $end_date, $text);

    // Check to see if this slide should be the start slide.
    $this->checkStartSlide($start_date);

    $slide->setDisplayDate($this->buildDisplayDate());

    $slide->setGroup($this->buildGroup());

    $slide->setBackground($this->buildBackground());

    $media = $this->buildMedia();
    if (!empty($media)) {
      $slide->setMedia($media);
    }

    $slide->setUniqueId($this->buildUniqueId());

    return $slide;
  }

  /**
   * Builds a timeline title slide from the current views data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\TitleSlide
   *   A slide object.
   */
  protected function buildTitleSlide() {
    $text = $this->buildText();
    $slide = new TitleSlide($text);

    $slide->setBackground($this->buildBackground());

    $media = $this->buildMedia();
    if (!empty($media)) {
      $slide->setMedia($media);
    }

    $slide->setUniqueId($this->buildUniqueId());

    return $slide;
  }

  /**
   * Builds a timeline era from the current views data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Era|null
   *   An era object or NULL if the start or end date could not be parsed.
   */
  protected function buildEra() {
    $start_date = $this->buildDate($this->options['timeline_fields']['start_date']);
    // Return NULL if the era has no start date.
    if (empty($start_date)) {
      return NULL;
    }
    $end_date = $this->buildDate($this->options['timeline_fields']['end_date']);
    // Return NULL if the era has no end date.
    if (empty($end_date)) {
      return NULL;
    }
    $text = $this->buildText();
    return new Era($start_date, $end_date, $text);
  }

  /**
   * Builds a timeline date from the current data row.
   *
   * @param string $field
   *   The machine name of the date field.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Date|null
   *   A date object or NULL if the start date could not be parsed.
   */
  protected function buildDate($field) {
    try {
      $date_markup = $this->getField($this->view->row_index, $field);
      if (empty($date_markup)) {
        return NULL;
      }
      // Store the date string so that it can be used in the error message, if
      // necessary.  Strip HTML tags from dates so users don't run into problems
      // like Date fields wrapping their output with metadata.
      $date_string = strip_tags($date_markup->__toString());
      $date = new Date($date_string);
    }
    catch (Exception $e) {
      // Return NULL if the field didn't contain a parseable date string.
      // @todo: Implement a logger.
      $this->messenger()->addMessage($this->t('The date "@date" does not conform to a <a href="@php-manual">PHP supported date and time format</a>.', ['@date' => $date_string, '@php-manual' => 'http://php.net/manual/en/datetime.formats.php']));
      $date = NULL;
    }
    return $date;
  }

  /**
   * Builds a timeline display date from the current data row.
   *
   * @return string
   *   A string which contains the text to be displayed instead of the start
   *   and end dates of a slide.
   */
  protected function buildDisplayDate() {
    $display_date = '';
    if ($this->options['timeline_fields']['display_date']) {
      $display_date_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['display_date']);
      $display_date = $display_date_markup ? $display_date_markup->__toString() : '';
    }
    return $display_date;
  }

  /**
   * Builds timeline text from the current data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Text
   *   A text object.
   */
  protected function buildText() {
    $headline = '';
    if ($this->options['timeline_fields']['headline']) {
      $headline_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['headline']);
      $headline = $headline_markup ? $headline_markup->__toString() : '';
    }

    $text = '';
    if ($this->options['timeline_fields']['text']) {
      $text_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['text']);
      $text = $text_markup ? $text_markup->__toString() : '';
    }
    return new Text($headline, $text);
  }

  /**
   * Builds a timeline group from the current data row.
   *
   * @return string
   *   The group name.
   */
  protected function buildGroup() {
    $group = '';
    if ($this->options['timeline_fields']['group']) {
      $group_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['group']);
      $group = $group_markup ? $group_markup->__toString() : '';
    }
    return $group;
  }

  /**
   * Builds a timeline background from the current data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Background
   *   A background object.
   */
  protected function buildBackground() {
    $url = '';
    if ($this->options['timeline_fields']['background']) {
      $url_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['background']);
      $url = $url_markup ? $url_markup->__toString() : '';

      // Special handling because core Image fields have no raw URL formatter.
      // Check to see if we don't have a raw URL.
      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        // Attempt to extract a URL from an img or anchor tag in the string.
        $url = $this->extractUrl($url);
      }
    }

    $color = '';
    if ($this->options['timeline_fields']['background_color']) {
      $color_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['background_color']);
      $color = $color_markup ? $color_markup->__toString() : '';
    }
    return new Background($url, $color);
  }

  /**
   * Builds timeline media from the current data row.
   *
   * @return \Drupal\views_timelinejs\TimelineJS\Media|null
   *   A media object or NULL if the URL is empty.
   */
  protected function buildMedia() {
    $url = '';
    if ($this->options['timeline_fields']['media']) {
      $url_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['media']);
      $url = $url_markup ? $url_markup->__toString() : '';

      // Special handling because core Image fields have no raw URL formatter.
      // Check to see if we don't have a raw URL.
      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        // Attempt to extract a URL from an img or anchor tag in the string.
        $url = $this->extractUrl($url);
      }
    }
    // Return NULL if the URL is empty.
    if (empty($url)) {
      return NULL;
    }

    $media = new Media($url);
    if ($this->options['timeline_fields']['thumbnail']) {
      $thumbnail_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['thumbnail']);
      $thumbnail = $thumbnail_markup ? $thumbnail_markup->__toString() : '';

      // Special handling because core Image fields have no raw URL formatter.
      // Check to see if we don't have a raw URL.
      if (!filter_var($thumbnail, FILTER_VALIDATE_URL)) {
        // Attempt to extract a URL from an img or anchor tag in the string.
        $thumbnail = $this->extractUrl($thumbnail);
      }
      $media->setThumbnail($thumbnail);
    }
    if ($this->options['timeline_fields']['caption']) {
      $caption_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['caption']);
      $caption = $caption_markup ? $caption_markup->__toString() : '';
      $media->setCaption($caption);
    }
    if ($this->options['timeline_fields']['credit']) {
      $credit_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['credit']);
      $credit = $credit_markup ? $credit_markup->__toString() : '';
      $media->setCredit($credit);
    }
    return $media;
  }

  /**
   * Builds a timeline unique id from the current data row.
   *
   * @return string
   *   A unique ID for a slide.
   */
  protected function buildUniqueId() {
    $unique_id = '';
    if ($this->options['timeline_fields']['unique_id']) {
      $unique_id_markup = $this->getField($this->view->row_index, $this->options['timeline_fields']['unique_id']);
      $unique_id = $unique_id_markup ? $unique_id_markup->__toString() : '';
    }
    return $unique_id;
  }

  /**
   * Checks a slide date to see if it should be displayed first in the timeline.
   *
   * @param \DateTime $date
   *   A date from a TimelineJS slide.
   */
  protected function checkStartSlide(DateTime $date) {
    static $smallest_difference;
    if (!isset($smallest_difference)) {
      $smallest_difference = NULL;
    }

    $timestamp = $date->getTimestamp();
    // Return if the date was prior to the UNIX Epoch.
    if ($timestamp === FALSE) {
      return;
    }

    // Calculate the absolute difference between the current time and the date.
    $difference = abs(time() - $timestamp);

    // Update the start slide index if this date is closer to the current time.
    if ($smallest_difference == NULL || $difference < $smallest_difference) {
      $smallest_difference = $difference;
      $this->startSlideIndex = $this->view->row_index;
    }
  }

  /**
   * Searches a string for HTML attributes that contain URLs and returns them.
   *
   * This will search a string which is presumed to contain HTML for anchor or
   * image tags.  It will return the href or src attribute of the first one it
   * finds.
   *
   * This is basically special handling for core Image fields.  There is no
   * built-in field formatter for outputting a raw URL from an image.  This
   * method allows image fields to "just work" as sources for TimelineJS media
   * and background image URLs.  Anchor tag handling was added for people who
   * forget to output link fields as plain text URLs.
   *
   * @param string $html
   *   A string that contains HTML.
   *
   * @return string
   *   A URL if one was found in the input string, the original string if not.
   */
  protected function extractUrl($html) {
    if (!empty($html)) {
      // Disable libxml errors.
      $previous_use_errors = libxml_use_internal_errors(TRUE);

      $document = new DOMDocument();
      $document->loadHTML($html);

      // Handle XML errors.
      foreach (libxml_get_errors() as $error) {
        $this->handleXmlErrors($error, $html);
      }

      // Restore the previous error setting.
      libxml_use_internal_errors($previous_use_errors);

      // Check for anchor tags.
      $anchor_tags = $document->getElementsByTagName('a');
      if ($anchor_tags->length) {
        return $anchor_tags->item(0)->getAttribute('href');
      }

      // Check for image tags.
      $image_tags = $document->getElementsByTagName('img');
      if ($image_tags->length) {
        return $image_tags->item(0)->getAttribute('src');
      }
    }
    return $html;
  }

  /**
   * Sets Drupal messages to inform users of HTML parsing errors.
   *
   * @param \LibXMLError $error
   *   Contains information about the XML parsing error.
   * @param mixed $html
   *   Contains the original HTML that was parsed.
   */
  protected function handleXmlErrors(\LibXMLError $error, $html) {
    $message = $this->t('A media field has an error in its HTML.<br>Error message: @message<br>Views result row: @row<br>HTML: <pre>@html</pre>', ['@message' => $error->message, '@row' => $this->view->row_index, '@html' => $html]);
    $this->messenger()->addWarning($message);
  }

  /**
   * Processes timeline options before theming.
   */
  protected function prepareTimelineOptions() {
    // Set the script_path option for locally-hosted libraries.
    if ($this->configuration['library_location'] == 'local') {
      $this->prepareScriptPathOption();
    }

    // Set the language option to the site's default if it is empty and the
    // language is supported.
    if (empty($this->options['timeline_config']['language'])) {
      $this->prepareLanguageOption();
    }

    // If the custom start_at_current option is set, then set the timeline's
    // start_at_slide option to the start_slide_index and override the
    // start_at_end option.
    if ($this->options['additional_config']['start_at_current']) {
      $this->options['timeline_config']['start_at_slide'] = $this->startSlideIndex;
      $this->options['timeline_config']['start_at_end'] = FALSE;
    }
  }

  /**
   * Sets the timeline language option to the site's current language.
   */
  protected function prepareLanguageOption() {
    $supported_languages = _views_timelinejs_list_languages();
    $language_map = _views_timelinejs_language_map();
    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Check for the site's current language in the list of languages that are
    // supported by TimelineJS.
    if (isset($supported_languages[$current_language])) {
      $this->options['timeline_config']['language'] = $current_language;
    }
    // Check for the site's current language in the list of language codes
    // that are different in Drupal than they are in TimelineJS.
    elseif (isset($language_map[$current_language])) {
      $this->options['timeline_config']['language'] = $language_map[$current_language];
    }
  }

  /**
   * Sets the timeline script_path option to the library's location.
   */
  protected function prepareScriptPathOption() {
    global $base_url;
    $script_path = $base_url . '/libraries/TimelineJS3/compiled/js';
    $this->options['timeline_config']['script_path'] = $script_path;
  }

}
