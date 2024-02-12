<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the link list link entity type.
 */
class LinkListLinkAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);
    if (!$access->isNeutral()) {
      return $access;
    }

    $type = $entity->bundle();
    switch ($operation) {
      case 'view':
        $permission = $entity->isPublished() ? 'view link list link' : 'view unpublished link list link';
        return AccessResult::allowedIfHasPermission($account, $permission)->addCacheableDependency($entity);

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit ' . $type . ' link list link');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete ' . $type . ' link list link');

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
      'create ' . $entity_bundle . ' link list link',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
