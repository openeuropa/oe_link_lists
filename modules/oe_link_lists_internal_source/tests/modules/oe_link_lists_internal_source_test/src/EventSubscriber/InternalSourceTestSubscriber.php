<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the event for altering the InternalSource plugin.
 */
class InternalSourceTestSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * InternalSourceQuerySubscriberTest constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      InternalSourceQueryEvent::NAME => 'alterQuery',
    ];
  }

  /**
   * Alters the query.
   *
   * @param \Drupal\oe_link_lists_internal_source\Event\InternalSourceQueryEvent $event
   *   The event.
   */
  public function alterQuery(InternalSourceQueryEvent $event) {
    if (!$this->state->get('internal_source_query_test_enable', FALSE)) {
      // We don't want to apply the query alterations in all cases but control
      // it from the test.
      return;
    }

    $query = $event->getQuery();
    $configuration = $query->getMetaData('oe_link_lists_internal_source');
    // Set the query metadata onto the state so we can assert it in the test.
    $this->state->set('internal_source_query_test_metadata', $configuration);
    $query->condition('name', 'Entity one', '!=');
  }

}
