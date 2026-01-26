<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\EventSubscriber;

use Drupal\oe_link_lists\Event\DisplayPluginDefaultResolverEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test subscriber forcing "title" as default display plugin when available.
 */
final class LinkListTestDefaultDisplayPluginSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DisplayPluginDefaultResolverEvent::NAME => 'onResolveDefault',
    ];
  }

  /**
   * Sets the default display plugin for test scenarios.
   */
  public function onResolveDefault(DisplayPluginDefaultResolverEvent $event): void {
    if (in_array('title', $event->getAvailablePluginIds(), TRUE)) {
      $event->setDefaultPluginId('title');
    }
  }

}
