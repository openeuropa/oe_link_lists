<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;

/**
 * Provides an interface for Link list link entities.
 *
 * @ingroup oe_link_lists
 */
interface LinkListLinkInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Gets the Link list link teaser.
   *
   * @return string|null
   *   Teaser of the Link list link.
   */
  public function getTeaser(): ?string;

  /**
   * Sets the Link list link teaser.
   *
   * @param string $teaser
   *   The Link list link teaser.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The called Link list link entity.
   */
  public function setTeaser(string $teaser): LinkListLinkInterface;

  /**
   * Gets the Link list link title.
   *
   * @return string|null
   *   Title of the Link list link.
   */
  public function getTitle(): ?string;

  /**
   * Sets the Link list link title.
   *
   * @param string $title
   *   The Link list link title.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The called Link list link entity.
   */
  public function setTitle(string $title): LinkListLinkInterface;

  /**
   * Gets the Link list link creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Link list link.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the Link list link creation timestamp.
   *
   * @param int $timestamp
   *   The Link list link creation timestamp.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   The called Link list link entity.
   */
  public function setCreatedTime(int $timestamp): LinkListLinkInterface;

  /**
   * Set the parent entity of the link.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   The parent entity.
   * @param string $parent_field_name
   *   The parent field name.
   *
   * @return $this
   */
  public function setParentEntity(ContentEntityInterface $parent, $parent_field_name);

}
