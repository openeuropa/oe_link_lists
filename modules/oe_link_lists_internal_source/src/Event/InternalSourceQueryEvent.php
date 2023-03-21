<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_internal_source\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Event used for altering the InternalSource plugin query.
 */
class InternalSourceQueryEvent extends Event {

  const NAME = 'oe_link_lists.internal_source_query_event';

  /**
   * The query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $query;

  /**
   * InternalSourceQueryEvent constructor.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query.
   */
  public function __construct(QueryInterface $query) {
    $this->query = $query;
  }

  /**
   * Returns the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query.
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

}
