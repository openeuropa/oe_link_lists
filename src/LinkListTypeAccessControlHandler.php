<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the link list type entity type.
 */
class LinkListTypeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view label':
        $permissions = [
          $this->entityType->getAdminPermission(),
          'view link list',
          'view unpublished link list',
        ];

        return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      default:
        return parent::checkAccess($entity, $operation, $account);
    }
  }

}
