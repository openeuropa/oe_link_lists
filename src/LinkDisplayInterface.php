<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for link_display plugins.
 */
interface LinkDisplayInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Builds a render array for a list of links.
   *
   * @param \Drupal\oe_link_lists\LinkCollectionInterface $links
   *   The link objects.
   *
   * @return array
   *   The render array.
   */
  public function build(LinkCollectionInterface $links): array;

  /**
   * Called when parent entity's presave hook is invoked.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity.
   */
  public function preSave(ContentEntityInterface $entity): void;

}
