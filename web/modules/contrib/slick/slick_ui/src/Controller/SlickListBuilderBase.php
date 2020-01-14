<?php

namespace Drupal\slick_ui\Controller;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\slick\SlickManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Slick optionsets.
 */
abstract class SlickListBuilderBase extends DraggableListBuilder {

  /**
   * The slick manager.
   *
   * @var \Drupal\slick\SlickManagerInterface
   */
  protected $manager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new SlickListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\slick\SlickManagerInterface $manager
   *   The slick manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, MessengerInterface $messenger, SlickManagerInterface $manager) {
    parent::__construct($entity_type, $storage);
    $this->manager = $manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('messenger'),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Configure');
    }

    $operations['duplicate'] = [
      'title'  => $this->t('Duplicate'),
      'weight' => 15,
      'url'    => $entity->toUrl('duplicate-form'),
    ];

    if ($entity->id() == 'default') {
      unset($operations['delete'], $operations['edit']);
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->messenger->addMessage($this->t('The optionsets order has been updated.'));
  }

}
