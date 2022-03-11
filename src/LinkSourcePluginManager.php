<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * LinkSource plugin manager.
 */
class LinkSourcePluginManager extends DefaultPluginManager implements LinkSourcePluginManagerInterface {

  use LinkListPluginManagerTrait;

  /**
   * Constructs LinkSourcePluginManager object.
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
      'Plugin/LinkSource',
      $namespaces,
      $module_handler,
      'Drupal\oe_link_lists\LinkSourceInterface',
      'Drupal\oe_link_lists\Annotation\LinkSource'
    );
    $this->alterInfo('link_source_info');
    $this->setCacheBackend($cache_backend, 'link_source_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginsAsOptions(string $bundle = NULL): array {
    return $this->getPluginsAsOptionsByBundle($bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedPlugins(): array {
    $definitions = $this->getDefinitions();
    $plugins = [];
    foreach ($definitions as $name => $definition) {
      if (isset($definition['deprecated']) && $definition['deprecated'] === TRUE) {
        // For new link lists, do not include the deprecated options.
        $plugins[] = $name;
      }
    }

    return $plugins;
  }

}
