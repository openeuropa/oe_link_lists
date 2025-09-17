<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists_manual_source\LinkListLinkAccessControlHandler as OriginalLinkListLinkAccessControlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the link list link entity type.
 */
class LinkListLinkAccessControlHandler extends OriginalLinkListLinkAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new LinkListLinkAccessControlHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Get the parent link list entity.
    $parent_id = $entity->get('parent_id')->value;
    $parent_entity = $this->entityTypeManager->getStorage('link_list')->load($parent_id);
    if ($parent_entity instanceof LinkListInterface) {
      // Delegate access check to the parent link list entity.
      return $parent_entity->access($operation, $account, TRUE);
    }

    // Fallback to parent.
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Try to get the parent link list from the route match.
    $parent_entity = $this->routeMatch->getParameter('link_list');
    if ($parent_entity instanceof LinkListInterface) {
      // Delegate create access check to the parent link list entity.
      return $parent_entity->access('update', $account, TRUE);
    }

    // If there is no parent entity we try to get the group from the route.
    $group = $this->routeMatch->getParameter('group');
    if ($group instanceof GroupInterface) {
      return $group->hasPermission('create group_link_list:manual entity', $account) ? AccessResult::allowed() : AccessResult::forbidden('User does not have permission to create manual link lists in this group.');
    }

    // Fallback to standard permission-based access.
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
