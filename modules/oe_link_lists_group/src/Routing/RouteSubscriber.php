<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_group\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Group Link List routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.group_content.create_page')) {
      $copy = clone $route;
      $copy->setPath('group/{group}/link-list/create');
      $copy->setDefault('base_plugin_id', 'group_link_list');
      $copy->setOption('_group_operation_route', TRUE);
      $collection->add('entity.group_content.group_link_list_create_page', $copy);
    }

    if ($route = $collection->get('entity.group_content.add_page')) {
      $copy = clone $route;
      $copy->setPath('group/{group}/list/add');
      $copy->setDefault('base_plugin_id', 'group_link_list');
      $copy->setOption('_group_operation_route', TRUE);
      $collection->add('entity.group_content.group_link_list_add_page', $copy);
    }
  }

}
