<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common entity utilities to work with field details.
 *
 * @see Drupal\blazy\Dejavu\BlazyEntityReferenceBase
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 */
class BlazyEntity {

  /**
   * The blazy oembed service.
   *
   * @var object
   */
  protected $oembed;

  /**
   * The blazy manager service.
   *
   * @var object
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyFormatter instance.
   */
  public function __construct(BlazyOEmbed $oembed) {
    $this->oembed = $oembed;
    $this->blazyManager = $oembed->blazyManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('blazy.oembed')
    );
  }

  /**
   * Returns the blazy oembed service.
   */
  public function oembed() {
    return $this->oembed;
  }

  /**
   * Build image/video preview either using theme_blazy(), or view builder.
   *
   * This is alternative to Drupal\blazy\BlazyFormatterManager used outside
   * field managers, such as Views field, or Entity Browser displays, etc.
   *
   * @param array $data
   *   An array of data containing settings, and image item.
   * @param object $entity
   *   The media entity, else file entity to be associated to media if any.
   * @param string $fallback
   *   The fallback string to display such as file name or entity label.
   *
   * @return array
   *   The renderable array of theme_blazy(), or view builder, else empty array.
   */
  public function build(array $data, $entity, $fallback = '') {
    $build = [];

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    // Supports core Media via Drupal\blazy\BlazyOEmbed::getMediaItem().
    $this->oembed->getMediaItem($data, $entity);

    $settings = &$data['settings'];

    if (!empty($data['item'])) {
      if (!empty($settings['media_switch'])) {
        $is_lightbox = $this->blazyManager->getLightboxes() && in_array($settings['media_switch'], $this->blazyManager->getLightboxes());
        $settings['lightbox'] = $is_lightbox ? $settings['media_switch'] : FALSE;
      }
      if (empty($settings['uri'])) {
        $settings['uri'] = ($file = $data['item']->entity) && empty($data['item']->uri) ? $file->getFileUri() : $data['item']->uri;
      }

      // Provide Blazy, if required.
      $build = $this->blazyManager->getBlazy($data);

      // Provides a shortcut to get URI.
      $build['#uri'] = $settings['uri'];

      // Allows top level elements to load Blazy once rather than per field.
      // This is still here for non-supported Views style plugins, etc.
      if (empty($settings['_detached'])) {
        $load = $this->blazyManager->attach($settings);

        // Enforces loading elements hidden by EB "Show selected" button.
        // @todo figure out to limit to EB plugins to avoid loadInvisible here,
        // currently relying on ambiguous `_detached` flag.
        $load['drupalSettings']['blazy']['loadInvisible'] = TRUE;
        $build['#attached'] = $load;
      }
    }
    else {
      $build = $this->getEntityView($entity, $settings, $fallback);
    }

    return $build;
  }

  /**
   * Returns the entity view, if available.
   *
   * @param object $entity
   *   The entity being rendered.
   * @param array $settings
   *   The settings containing view_mode.
   * @param string $fallback
   *   The fallback content when all fails, probably just entity label.
   *
   * @return array|bool
   *   The renderable array of the view builder, or false if not applicable.
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    if ($entity instanceof EntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      $view_hook      = $entity_type_id . '_view';
      $view_mode      = empty($settings['view_mode']) ? 'default' : $settings['view_mode'];
      $langcode       = $entity->language()->getId();

      // If module implements own {entity_type}_view.
      if (function_exists($view_hook)) {
        return $view_hook($entity, $view_mode, $langcode);
      }
      // If entity has view_builder handler.
      elseif ($this->blazyManager->getEntityTypeManager()->hasHandler($entity_type_id, 'view_builder')) {
        return $this->blazyManager->getEntityTypeManager()->getViewBuilder($entity_type_id)->view($entity, $view_mode, $langcode);
      }
      elseif ($fallback) {
        return ['#markup' => $fallback];
      }
    }

    return FALSE;
  }

  /**
   * Returns the string value of the fields: link, or text.
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    if ($entity->hasTranslation($langcode)) {
      // If the entity has translation, fetch the translated value.
      return $entity->getTranslation($langcode)->get($field_name)->getValue();
    }

    // Entity doesn't have translation, fetch original value.
    return $entity->get($field_name)->getValue();
  }

  /**
   * Returns the string value of the fields: link, or text.
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    $values = $this->getFieldValue($entity, $field_name, $langcode);

    // Can be text, or link field.
    $string = isset($values[0]['uri']) ? $values[0]['uri'] : (isset($values[0]['value']) ? $values[0]['value'] : '');

    if ($string && is_string($string)) {
      $string = $clean ? strip_tags($string, '<a><strong><em><span><small>') : Xss::filter($string, BlazyDefault::TAGS);
      return trim($string);
    }
    return '';
  }

  /**
   * Returns the formatted renderable array of the field.
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    if (isset($entity->{$field_name}) && !empty($entity->{$field_name}->view($view_mode)[0])) {
      $view = $entity->get($field_name)->view($view_mode);

      // Prevents quickedit to operate here as otherwise JS error.
      // @see 2314185, 2284917, 2160321.
      // @see quickedit_preprocess_field().
      // @todo: Remove when it respects plugin annotation.
      $view['#view_mode'] = '_custom';
      $weight = isset($view['#weight']) ? $view['#weight'] : 0;

      // Intentionally clean markups as this is not meant for vanilla.
      if ($multiple) {
        $items = [];
        foreach (Element::children($view) as $key) {
          $items[$key] = $entity->get($field_name)->view($view_mode)[$key];
        }

        $items['#weight'] = $weight;
        return $items;
      }
      return $view[0];
    }
    return [];
  }

  /**
   * Returns the text or link value of the fields: link, or text.
   */
  public function getFieldTextOrLink($entity, $field_name, $settings, $multiple = TRUE) {
    $langcode = $settings['langcode'];
    if ($text = $this->getFieldValue($entity, $field_name, $langcode)) {
      if (!empty($text[0]['value']) && !isset($text[0]['uri'])) {
        // Prevents HTML-filter-enabled text from having bad markups (h2 > p),
        // except for a few reasonable tags acceptable within H2 tag.
        $text = $this->getFieldString($entity, $field_name, $langcode, FALSE);
      }
      elseif (isset($text[0]['uri']) && !empty($text[0]['title'])) {
        $text = $this->getFieldRenderable($entity, $field_name, $settings['view_mode'], $multiple);
      }

      // Prevents HTML-filter-enabled text from having bad markups
      // (h2 > p), save for few reasonable tags acceptable within H2 tag.
      return is_string($text) ? ['#markup' => strip_tags($text, '<a><strong><em><span><small>')] : $text;
    }

    return [];
  }

}
