<?php

namespace Drupal\layout_builder_styles;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of layout builder style entities.
 */
class LayoutBuilderStyleListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['type'] = $entity->getType();
    return $row + parent::buildRow($entity);
  }

}
