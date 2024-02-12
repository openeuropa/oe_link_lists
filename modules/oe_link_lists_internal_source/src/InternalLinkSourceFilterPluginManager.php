<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a plugin manager for internal link source filter plugins.
 */
class InternalLinkSourceFilterPluginManager extends DefaultPluginManager implements InternalLinkSourceFilterPluginManagerInterface {

  /**
   * Constructs a InternalSourceFilterPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/InternalLinkSourceFilter',
      $namespaces,
      $module_handler,
      'Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface',
      'Drupal\oe_link_lists_internal_source\Annotation\InternalLinkSourceFilter'
    );
    $this->alterInfo('internal_link_source_filter_info');
    $this->setCacheBackend($cache_backend, 'internal_link_source_filter_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();

    // Sort definitions alphabetically by plugin ID.
    ksort($definitions);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicablePlugins(string $entity_type, string $bundle): array {
    $plugins = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      // If entity types are provided in the plugin definition, use them to
      // exclude early any non-applicable plugin.
      if (is_array($definition['entity_types']) && !empty($definition['entity_types'])) {
        // Skip the plugin if the current entity type is not supported.
        if (!array_key_exists($entity_type, $definition['entity_types'])) {
          continue;
        }

        // Keep the plugin only if no bundles were specified or if the current
        // bundle is supported.
        if (
          is_array($definition['entity_types'][$entity_type]) &&
          !empty($definition['entity_types'][$entity_type]) &&
          !in_array($bundle, $definition['entity_types'][$entity_type])
        ) {
          continue;
        }
      }

      /** @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface $plugin */
      $plugin = $this->createInstance($plugin_id);
      if ($plugin->isApplicable($entity_type, $bundle)) {
        $plugins[$plugin_id] = $plugin;
      }
    }

    return $plugins;
  }

}
