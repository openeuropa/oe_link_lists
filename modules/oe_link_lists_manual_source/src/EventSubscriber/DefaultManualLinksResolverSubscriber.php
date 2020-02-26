<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;
use Drupal\oe_link_lists_manual_source\Event\EntityValueOverrideResolverEvent;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkOverrideResolverEvent;
use Drupal\oe_link_lists_manual_source\Event\ManualLinksResolverEvent;
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
    return [ManualLinksResolverEvent::NAME => 'resolveLinks'];
  }

  /**
   * Resolves the link objects.
   *
   * @param \Drupal\oe_link_lists_manual_source\Event\ManualLinksResolverEvent $event
   *   The event.
   */
  public function resolveLinks(ManualLinksResolverEvent $event): void {
    $link_entities = $event->getLinkEntities();
    if (!$link_entities) {
      return;
    }

    $links = new LinkCollection();
    foreach ($link_entities as $link_entity) {
      $link = $this->getLinkFromEntity($link_entity);
      if ($link) {
        $link->addCacheableDependency($link_entity);
        $links[] = $link;
      }
    }

    if ($links) {
      $event->setLinks($links);
    }
  }

  /**
   * Turns a list link into a link object.
   *
   * For internal links we default to using the title and body fields if there
   * are not overrides in place. It is the responsibility of other subscribers.
   *
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_entity
   *   The link entity.
   *
   * @return \Drupal\oe_link_lists\LinkInterface|null
   *   The link object.
   */
  protected function getLinkFromEntity(LinkListLinkInterface $link_entity): ?LinkInterface {
    $link_entity = $this->entityRepository->getTranslationFromContext($link_entity);

    $url = $link_entity->hasField('target') && $link_entity->get('target')->entity instanceof EntityInterface ? $link_entity->get('target')->entity->toUrl() : NULL;
    if ($url) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
      $referenced_entity = $link_entity->get('target')->entity;
      $referenced_entity = $this->entityRepository->getTranslationFromContext($referenced_entity);
      $event = new EntityValueResolverEvent($referenced_entity);
      $this->eventDispatcher->dispatch(EntityValueResolverEvent::NAME, $event);
      $link = $event->getLink();
      $link->addCacheableDependency($referenced_entity);

      // Override the title and teaser.
      if (!$link_entity->get('title')->isEmpty()) {
        $link->setTitle($link_entity->getTitle());
      }
      if (!$link_entity->get('teaser')->isEmpty()) {
        $link->setTeaser(['#markup' => $link_entity->getTeaser()]);
      }

      // Dispatch an event to allow others to perform their overrides.
      $event = new EntityValueOverrideResolverEvent($referenced_entity, $link_entity, $link);
      $this->eventDispatcher->dispatch(EntityValueOverrideResolverEvent::NAME, $event);
      return $event->getLink();
    }

    try {
      $url = Url::fromUri($link_entity->get('url')->uri);
    }
    catch (\InvalidArgumentException $exception) {
      // Normally this should not ever happen but just in case the data is
      // incorrect we want to construct a valid link object.
      $url = Url::fromRoute('<front>');
    }

    $link = new DefaultLink($url, $link_entity->getTitle(), ['#markup' => $link_entity->getTeaser()]);
    $event = new ManualLinkOverrideResolverEvent($link, $link_entity);
    $this->eventDispatcher->dispatch(ManualLinkOverrideResolverEvent::NAME, $event);

    return $event->getLink();

  }

}
