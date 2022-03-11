<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

/**
 * Trait used by link list related plugin managers for preparing plugins.
 */
trait LinkListPluginManagerTrait {

  /**
   * Returns a list of plugins to be used as form options.
   *
   * It uses plugin id as key and plugin label as value.
   *
   * @param string|null $bundle
   *   The bundle to retrieve the plugins for.
   *
   * @return array
   *   The options.
   */
  protected function getPluginsAsOptionsByBundle(string $bundle = NULL): array {
    $definitions = $this->getDefinitions();
    $options = [];
    foreach ($definitions as $name => $definition) {
      $internal = $definition['internal'] ?? FALSE;
      if ($internal) {
        continue;
      }

      if (!$bundle) {
        // If no bundle is passed, it means we can return them all.
        $options[$name] = $definition['label'];
        continue;
      }

      if (!is_array($definition['bundles']) || empty($definition['bundles'])) {
        // If the plugin has no restriction, we include it.
        $options[$name] = $definition['label'];
        continue;
      }

      if (in_array($bundle, $definition['bundles'])) {
        $options[$name] = $definition['label'];
      }
    }

    return $options;
  }

}
