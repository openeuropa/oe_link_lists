<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Event\EntityValueOverrideResolverEvent;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkOverrideResolverEvent;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Default subscriber that resolves links from a link list.
 */
class DefaultManualLinksResolverSubscriber implements EventSubscriberInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * DefaultManualLinkResolverSubscriber constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, EntityRepositoryInterface $entityRepository) {
    $this->eventDispatcher = $eventDispatcher;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [ManualLinkResolverEvent::NAME => 'resolveLink'];
  }

  /**
   * Resolves a manual link object from a link list link entity.
   *
   * @param \Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent $event
   *   The event.
   */
  public function resolveLink(ManualLinkResolverEvent $event): void {
    $bundles = [
      'internal' => [$this, 'resolveInternalLink'],
      'external' => [$this, 'resolveExternalLink'],
    ];

    $bundle = $event->getLinkEntity()->bundle();
    if (!isset($bundles[$bundle])) {
      return;
    }

    $callback = $bundles[$bundle];
    $link = call_user_func($callback, $event);
    if ($link) {
      $event->setLink($link);
    }
  }

  /**
   * Resolves an internal link.
   *
   * @param \Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent $event
   *   The event.
   *
   * @return \Drupal\oe_link_lists\LinkInterface
   *   The link.
   */
  public function resolveInternalLink(ManualLinkResolverEvent $event): ?LinkInterface {
    $link_entity = $this->entityRepository->getTranslationFromContext($event->getLinkEntity());

    $referenced_entity = $link_entity->get('target')->entity;
    if (!$referenced_entity instanceof ContentEntityInterface) {
      return NULL;
    }

    $referenced_entity = $this->entityRepository->getTranslationFromContext($referenced_entity);
    $resolver_event = new EntityValueResolverEvent($referenced_entity);
    $this->eventDispatcher->dispatch($resolver_event, EntityValueResolverEvent::NAME);
    $link = $resolver_event->getLink();
    $link->addCacheableDependency($referenced_entity);

    // Override the title and teaser.
    if (!$link_entity->get('title')->isEmpty()) {
      $link->setTitle($link_entity->getTitle());
    }
    if (!$link_entity->get('teaser')->isEmpty()) {
      $link->setTeaser(['#markup' => $link_entity->getTeaser()]);
    }

    // Dispatch an event to allow others to perform their overrides.
    $override_event = new EntityValueOverrideResolverEvent($referenced_entity, $link_entity, $link);
    $this->eventDispatcher->dispatch($override_event, EntityValueOverrideResolverEvent::NAME);

    return $override_event->getLink();
  }

  /**
   * Resolves an external link.
   *
   * @param \Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent $event
   *   The event.
   *
   * @return \Drupal\oe_link_lists\LinkInterface
   *   The link.
   */
  public function resolveExternalLink(ManualLinkResolverEvent $event): LinkInterface {
    $link_entity = $this->entityRepository->getTranslationFromContext($event->getLinkEntity());

    try {
      $url = Url::fromUri($link_entity->get('url')->uri);
    }
    catch (\InvalidArgumentException $exception) {
      // Normally this should not ever happen but just in case the data is
      // incorrect we want to construct a valid link object.
      $url = Url::fromRoute('<front>');
    }

    $link = new DefaultLink($url, $link_entity->getTitle(), ['#markup' => $link_entity->getTeaser()]);
    $override_event = new ManualLinkOverrideResolverEvent($link, $link_entity);
    $this->eventDispatcher->dispatch($override_event, ManualLinkOverrideResolverEvent::NAME);

    return $override_event->getLink();
  }

}
