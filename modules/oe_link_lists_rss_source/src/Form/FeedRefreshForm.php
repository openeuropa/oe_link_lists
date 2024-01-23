<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_rss_source\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for refreshing the feeds: deleting the items and re-importing them.
 */
class FeedRefreshForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FeedRefreshForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_link_lists_rss_source_feed_refresh';
  }

  /**
   * Access callback for the form route.
   */
  public function access(AccountInterface $account, RouteMatchInterface $route_match) {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $route_match->getParameter('link_list');
    $access = $link_list->access('update', $account, TRUE);
    if (!$access->isAllowed()) {
      return $access;
    }

    if (!$link_list->isDefaultTranslation()) {
      // We should not be getting this on a translation.
      return AccessResult::forbidden()->addCacheableDependency($access);
    }

    $configuration = $link_list->getConfiguration();
    if (!isset($configuration['source']['plugin'])) {
      // Just in case it misses the plugin.
      return AccessResult::forbidden()->addCacheableDependency($access);
    }

    return $configuration['source']['plugin'] === 'rss_links' ? $access : AccessResult::forbidden()->addCacheableDependency($access);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, LinkListInterface $link_list = NULL) {
    $form['message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Are you sure you want to refresh the feeds?'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
    ];

    $form_state->set('link_list', $link_list);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->get('link_list');

    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');

    // Gather all the feeds from all translations.
    /** @var \Drupal\aggregator\FeedInterface[] $feeds */
    $feeds = [];
    foreach ($link_list->getTranslationLanguages() as $language) {
      $translation = $link_list->getTranslation($language->getId());
      $configuration = $translation->getConfiguration();
      $feeds += $feed_storage->loadByProperties(['url' => $configuration['source']['plugin_configuration']['urls']]);
    }

    foreach ($feeds as $feed) {
      $feed->refreshItems();
    }

    $this->messenger()->addStatus($this->formatPlural(count($feeds), '@count feed has been refreshed.', 'A number of @count feeds have been refreshed.'));
    $form_state->setRedirectUrl($link_list->toUrl());
  }

}
