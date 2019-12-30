<?php

namespace Drupal\layout_builder_styles\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\layout_builder_styles\LayoutBuilderStyleInterface;

/**
 * Defines the LayoutBuilderStyle config entity.
 *
 * @ConfigEntityType(
 *   id = "layout_builder_style",
 *   label = @Translation("Layout builder style"),
 *   label_collection = @Translation("Layout builder styles"),
 *   label_plural = @Translation("Layout builder styles"),
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "list_builder" = "Drupal\layout_builder_styles\LayoutBuilderStyleListBuilder",
 *     "form" = {
 *       "add" = "Drupal\layout_builder_styles\Form\LayoutBuilderStyleForm",
 *       "edit" = "Drupal\layout_builder_styles\Form\LayoutBuilderStyleForm",
 *       "delete" = "Drupal\layout_builder_styles\Form\LayoutBuilderStyleDeleteForm"
 *     }
 *   },
 *   config_prefix = "style",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "classes" = "classes",
 *     "type" = "type",
 *     "block_restrictions" = "block_restrictions",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/content/layout_builder_style/{layout_builder_style}/edit",
 *     "delete-form" = "/admin/config/content/layout_builder_style/{layout_builder_style}/delete",
 *     "collection" = "/admin/config/content/layout_builder_style",
 *     "add-form" = "/admin/config/content/layout_builder_style/add"
 *   }
 * )
 */
class LayoutBuilderStyle extends ConfigEntityBase implements LayoutBuilderStyleInterface {

  /**
   * The Layout Builder Style ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Layout Builder Style label.
   *
   * @var string
   */
  protected $label;

  /**
   * A string containing the classes, one per line.
   *
   * @var string
   */
  protected $classes;

  /**
   * A string indicating if this style applies to sections or components.
   *
   * @var string
   */
  protected $type;

  /**
   * A list of blocks to limit this style to.
   *
   * @var array
   */
  protected $block_restrictions;

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    return $this->classes;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockRestrictions() {
    return isset($this->block_restrictions) ? $this->block_restrictions : [];
  }

}
