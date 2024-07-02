<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that renders a link list.
 *
 * @Block(
 *   id = "oe_link_list_block",
 *   admin_label = @Translation("Link List"),
 *   category = @Translation("Link Lists"),
 *   deriver = "Drupal\oe_link_lists\Plugin\Derivative\LinkListBlock",
 * )
 */
class LinkListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LinkListBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_mode' => 'full',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    $link_list = $this->getLinkList();
    return $link_list->access('view', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $link_list = $this->getLinkList();
    if (!$link_list) {
      return [];
    }
    $builder = $this->entityTypeManager->getViewBuilder('link_list');
    // @todo once we determine how we render the link lists, adapt this and/or
    // make it configurable.
    return $builder->view($link_list, $this->configuration['view_mode']);
  }

  /**
   * Returns the derived link list.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface|null
   *   The link list entity.
   */
  protected function getLinkList(): ?LinkListInterface {
    $uuid = $this->getDerivativeId();
    $link_lists = $this->entityTypeManager->getStorage('link_list')->loadByProperties(['uuid' => $uuid]);
    if (!$link_lists) {
      // Normally, this should not happen but in case the entity has been
      // deleted.
      return NULL;
    }

    return reset($link_lists);
  }

}
