<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for link_source plugins.
 */
interface LinkSourceInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Returns a list of links.
   *
   * @param int|null $limit
   *   The number of items to return.
   * @param int $offset
   *   An offset from the default result set.
   *
   * @return \Drupal\oe_link_lists\LinkCollectionInterface
   *   A list of links.
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface;

  /**
   * Called when parent entity's presave hook is invoked.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The parent entity.
   */
  public function preSave(EntityInterface $entity = NULL): void;

}
