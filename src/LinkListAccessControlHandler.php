<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the link list entity type.
 */
class LinkListAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);
    if (!$access->isNeutral()) {
      return $access;
    }

    $type = $entity->bundle();
    switch ($operation) {
      case 'view':
        $permission = $entity->isPublished() ? 'view link list' : 'view unpublished link list';
        return AccessResult::allowedIfHasPermission($account, $permission)->addCacheableDependency($entity);

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit ' . $type . ' link list');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete ' . $type . ' link list');

      case 'view all revisions':
        return AccessResult::allowedIfHasPermission($account, 'view any ' . $type . ' link list revisions');

      case 'view revision':
        return $entity->access('view', $account, TRUE);

      case 'revert':
        return AccessResult::allowedIfHasPermission($account, 'revert any ' . $type . ' link list revisions');

      case 'delete revision':
        return AccessResult::allowedIfHasPermission($account, 'delete any ' . $type . ' link list revisions');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      $this->entityType->getAdminPermission(),
      'create ' . $entity_bundle . ' link list',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
