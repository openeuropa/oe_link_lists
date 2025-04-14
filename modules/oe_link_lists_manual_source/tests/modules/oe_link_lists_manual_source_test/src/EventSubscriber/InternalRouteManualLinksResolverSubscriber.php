<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source_test\EventSubscriber;

use Drupal\Core\State\State;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests subscriber that resolves internal route manual link entities.
 */
class InternalRouteManualLinksResolverSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * InternalRouteManualLinksResolverSubscriber constructor.
   *
   * @param \Drupal\Core\State\State $state
   *   The state service.
   */
  public function __construct(State $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ManualLinkResolverEvent::NAME => ['resolveLink', 10],
    ];
  }

  /**
   * Resolves a manual link object from a link list link entity.
   *
   * @param \Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent $event
   *   The event.
   */
  public function resolveLink(ManualLinkResolverEvent $event): void {
    $link_entity = $event->getLinkEntity();
    if ($link_entity->bundle() !== 'internal_route') {
      return;
    }

    // We want to control from the test whether the links should be resolved.
    if (!$this->state->get('oe_link_lists_manual_source_test_subscriber_resolve', FALSE)) {
      return;
    }

    try {
      $url = Url::fromUri($link_entity->get('url')->uri);
    }
    catch (\InvalidArgumentException $exception) {
      $url = Url::fromRoute('<front>');
    }

    $link = new DefaultLink($url, $link_entity->getTitle(), ['#markup' => $link_entity->getTeaser()]);
    $event->setLink($link);
    $event->stopPropagation();
  }

}
