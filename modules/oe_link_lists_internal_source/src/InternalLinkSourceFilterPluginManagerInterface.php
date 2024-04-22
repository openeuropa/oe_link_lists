<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Provides an interface for internal link source filter plugin managers.
 */
interface InternalLinkSourceFilterPluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns plugins applicable to the specified entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface[]
   *   A list of plugin instances, keyed by plugin ID.
   */
  public function getApplicablePlugins(string $entity_type, string $bundle): array;

}
