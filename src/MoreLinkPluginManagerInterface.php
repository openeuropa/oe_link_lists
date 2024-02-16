<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for more_link plugin managers.
 */
interface MoreLinkPluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns a list of plugins to be used as form options.
   *
   * It uses plugin id as key and plugin label as value.
   *
   * @param string $bundle
   *   The bundle to retrieve the plugins for.
   *
   * @return array
   *   The options.
   */
  public function getPluginsAsOptions(string $bundle = NULL): array;

}
