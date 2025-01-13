<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for link_display plugin managers.
 */
interface LinkDisplayPluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns a list of plugins to be used as form options.
   *
   * It uses plugin id as key and plugin label as value.
   *
   * @param string $bundle
   *   The bundle to retrieve the plugins for.
   * @param string|null $link_source
   *   The link source the display plugin should work with.
   *
   * @return array
   *   The options.
   */
  public function getPluginsAsOptions(?string $bundle = NULL, ?string $link_source = NULL): array;

}
