<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for Link list entities.
 *
 * @ingroup oe_link_lists
 */
interface LinkListInterface extends ContentEntityInterface {

  /**
   * Gets the link list title.
   *
   * @return string
   *   Title of the link list.
   */
  public function getTitle(): ?string;

  /**
   * Sets the link list title.
   *
   * @param string $title
   *   The link list title.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   The called link list entity.
   */
  public function setTitle(string $title): LinkListInterface;

  /**
   * Gets the link list administrative title.
   *
   * @return string
   *   Administrative title of the link list.
   */
  public function getAdministrativeTitle(): string;

  /**
   * Sets the link list administrative title.
   *
   * @param string $administrativeTitle
   *   The link list administrative title.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   The called link list entity.
   */
  public function setAdministrativeTitle(string $administrativeTitle): LinkListInterface;

  /**
   * Gets the link list configuration.
   *
   * @return array
   *   Configuration of the link list.
   */
  public function getConfiguration(): array;

  /**
   * Sets the link list configuration.
   *
   * @param array $configuration
   *   The link list configuration.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   The called link list entity.
   */
  public function setConfiguration(array $configuration): LinkListInterface;

  /**
   * Gets the link list creation timestamp.
   *
   * @return int
   *   Creation timestamp of the link list.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the link list creation timestamp.
   *
   * @param int $timestamp
   *   The link list creation timestamp.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   The called link list entity.
   */
  public function setCreatedTime(int $timestamp): LinkListInterface;

  /**
   * Gets the link list revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the link list revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   The called link list entity.
   */
  public function setRevisionCreationTime($timestamp);

}
