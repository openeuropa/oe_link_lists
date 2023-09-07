<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Event;

use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event class used for resolving a manually created link into a link object.
 */
class ManualLinkResolverEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_link_lists.event.manual_link_resolver';

  /**
   * The link entity.
   *
   * @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   */
  protected $linkEntity = [];

  /**
   * The resolved link.
   *
   * @var \Drupal\oe_link_lists\LinkInterface
   */
  protected $link = NULL;

  /**
   * ManualLinkResolverEvent constructor.
   *
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_entity
   *   The link entity.
   */
  public function __construct(LinkListLinkInterface $link_entity) {
    $this->linkEntity = $link_entity;
  }

  /**
   * Returns the link entities.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The link entities.
   */
  public function getLinkEntity(): LinkListLinkInterface {
    return $this->linkEntity;
  }

  /**
   * Checks whether the link was resolved.
   *
   * @return bool
   *   Whether the link has been resolved or not.
   */
  public function hasLink(): bool {
    return $this->link instanceof LinkInterface;
  }

  /**
   * Returns the link object.
   *
   * @return \Drupal\oe_link_lists\LinkInterface
   *   The link object.
   */
  public function getLink(): LinkInterface {
    return $this->link;
  }

  /**
   * Sets the link object.
   *
   * @param \Drupal\oe_link_lists\LinkInterface $link
   *   The link object.
   */
  public function setLink(LinkInterface $link): void {
    $this->link = $link;
  }

}
