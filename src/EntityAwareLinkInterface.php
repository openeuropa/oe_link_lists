<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Represent entity-aware links returned by LinkSource plugins.
 *
 * @see \Drupal\oe_link_lists\LinkSourceInterface
 */
interface EntityAwareLinkInterface extends LinkInterface {

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  public function getEntity(): ContentEntityInterface;

  /**
   * Sets the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   */
  public function setEntity(ContentEntityInterface $entity): void;

}
