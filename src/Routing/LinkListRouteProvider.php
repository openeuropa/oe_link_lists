<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides Link lists routes.
 */
class LinkListRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCollectionRoute($entity_type)) {
      $route->setRequirement('_permission', 'access link list overview');
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCanonicalRoute($entity_type)) {
      $route->setRequirement('_permission', 'access link list canonical page');
      return $route;
    }
  }

}
