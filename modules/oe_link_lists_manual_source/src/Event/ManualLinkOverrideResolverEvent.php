<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;

/**
 * Allows overrides for individual link objects that have been resolved.
 *
 * This allows their transformation to contain more data.
 */
class ManualLinkOverrideResolverEvent extends Event {

  const NAME = 'oe_link_lists.event.manual_link_override_resolver';

  /**
   * The link object.
   *
   * @var \Drupal\oe_link_lists\LinkInterface
   */
  protected $link;

  /**
   * The link entity.
   *
   * @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   */
  protected $linkEntity;

  /**
   * ManualLinkOverrideResolverEvent constructor.
   *
   * @param \Drupal\oe_link_lists\LinkInterface $link
   *   The link object.
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_entity
   *   The link entity.
   */
  public function __construct(LinkInterface $link, LinkListLinkInterface $link_entity) {
    $this->link = $link;
    $this->linkEntity = $link_entity;
  }

  /**
   * Returns the link entity.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The link entity.
   */
  public function getLinkEntity(): LinkListLinkInterface {
    return $this->linkEntity;
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
