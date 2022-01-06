<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;

/**
 * Interface for more_link plugins.
 */
interface MoreLinkInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Returns the More link.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache metadata built when rendering the list.
   *
   * @return \Drupal\Core\Link|null
   *   The link object or NULL if one could not be generated.
   */
  public function getLink(LinkListInterface $link_list, CacheableMetadata $cache): ?Link;

}
