<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;

/**
 * Event used to resolve the overrides of entity values on a link object.
 */
class EntityValueOverrideResolverEvent extends EntityValueResolverEvent {

  /**
   * The name of the event.
   */
  const NAME = 'oe_link_lists.event.entity_value_override_resolver';

  /**
   * The manual link entity.
   *
   * @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   */
  protected $linkEntity;

  /**
   * EntityValueOverrideResolverEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_entity
   *   The link entity.
   * @param \Drupal\oe_link_lists\LinkInterface $link
   *   The link object.
   */
  public function __construct(ContentEntityInterface $entity, LinkListLinkInterface $link_entity, LinkInterface $link) {
    parent::__construct($entity);
    $this->linkEntity = $link_entity;
    $this->link = $link;
  }

  /**
   * Gets the link entity.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The link entity.
   */
  public function getLinkEntity(): LinkListLinkInterface {
    return $this->linkEntity;
  }

  /**
   * Sets the link entity.
   *
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_entity
   *   The link entity.
   */
  public function setLinkEntity(LinkListLinkInterface $link_entity): void {
    $this->linkEntity = $link_entity;
  }

}
