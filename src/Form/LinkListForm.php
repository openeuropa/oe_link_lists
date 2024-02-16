<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Link list edit forms.
 *
 * @ingroup oe_link_lists
 */
class LinkListForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Constructs a new LinkListLinkForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user account.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, AccountProxyInterface $account, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->account = $account;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['#tree'] = TRUE;

    if (!$this->entity->isNew()) {
      $form['new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => TRUE,
        '#weight' => 10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);

    // Automatically set the link source plugin onto the link lists that have
    // been configured to have an auto-plugin.
    $configuration = $entity->getConfiguration();
    if (!isset($configuration['source'])) {
      $bundle = $this->entityTypeManager->getStorage('link_list_type')->load($this->entity->bundle());
      $auto_plugin = $bundle->getDefaultLinkSource();
      if (!$auto_plugin) {
        return $entity;
      }

      $configuration['source'] = [
        'plugin' => $auto_plugin,
        'plugin_configuration' => [],
      ];
      $entity->setConfiguration($configuration);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $entity = $this->entity;
    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('new_revision') && (bool) $form_state->getValue('new_revision') === FALSE) {
      $entity->setNewRevision(FALSE);
    }
    else {
      $entity->setNewRevision();
      // If a new revision is created, save also the revision metadata.
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->account->id());
    }

    $status = parent::save($form, $form_state);
    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the %label Link list.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger->addMessage($this->t('Saved the %label Link list.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.link_list.canonical', ['link_list' => $entity->id()]);
  }

}
