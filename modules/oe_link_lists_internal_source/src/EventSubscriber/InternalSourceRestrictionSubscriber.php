<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceBundlesEvent;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceEntityTypesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the events for altering the available entity type and bundle.
 */
class InternalSourceRestrictionSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * InternalSourceSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      InternalSourceEntityTypesEvent::NAME => 'alterEntityTypes',
      InternalSourceBundlesEvent::NAME => 'alterBundles',
    ];
  }

  /**
   * Alters the selectable entity types.
   *
   * @param \Drupal\oe_link_lists_internal_source\Event\InternalSourceEntityTypesEvent $event
   *   The event.
   */
  public function alterEntityTypes(InternalSourceEntityTypesEvent $event): void {
    $config = $this->configFactory->get('oe_link_lists_internal_source.settings');
    $allowed_entity_types = $config->get('allowed_entity_bundles');
    // Limit the selectable entity types.
    if (!$allowed_entity_types) {
      return;
    }

    $entity_types = array_keys($allowed_entity_types);

    $event->setEntityTypes(array_intersect($entity_types, $event->getEntityTypes()));
  }

  /**
   * Alters the selectable entity bundles.
   *
   * @param \Drupal\oe_link_lists_internal_source\Event\InternalSourceBundlesEvent $event
   *   The event.
   */
  public function alterBundles(InternalSourceBundlesEvent $event): void {
    $config = $this->configFactory->get('oe_link_lists_internal_source.settings');
    $allowed_entity_types = $config->get('allowed_entity_bundles');
    // Limit the selectable entity types.
    if (!$allowed_entity_types) {
      return;
    }
    $entity_type = $event->getEntityType();
    $allowed_bundles = $allowed_entity_types[$entity_type] ?? [];
    $event->setBundles(array_intersect($event->getBundles(), $allowed_bundles));
  }

}
