<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_link_lists\LinkInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event used to resolve the values used by the Link from an entity.
 *
 * This is meant to handle the basic data values: URL, title and teaser.
 */
class EntityValueResolverEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_link_lists.event.entity_value_resolver';

  /**
   * The content entity to get the values from.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The resulting link.
   *
   * @var \Drupal\oe_link_lists\LinkInterface
   */
  protected $link;

  /**
   * EntityValueResolverEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   */
  public function __construct(ContentEntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the link.
   *
   * @return \Drupal\oe_link_lists\LinkInterface
   *   The resulting link.
   */
  public function getLink(): LinkInterface {
    return $this->link;
  }

  /**
   * Sets the link.
   *
   * @param \Drupal\oe_link_lists\LinkInterface $link
   *   The resulting link.
   */
  public function setLink(LinkInterface $link): void {
    $this->link = $link;
  }

}
