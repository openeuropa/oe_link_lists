<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * LinkDisplay plugin manager.
 */
class LinkDisplayPluginManager extends DefaultPluginManager implements LinkDisplayPluginManagerInterface {

  use LinkListPluginManagerTrait;

  /**
   * Constructs LinkDisplayPluginManager object.
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
      'Plugin/LinkDisplay',
      $namespaces,
      $module_handler,
      'Drupal\oe_link_lists\LinkDisplayInterface',
      'Drupal\oe_link_lists\Annotation\LinkDisplay'
    );
    $this->alterInfo('link_display_info');
    $this->setCacheBackend($cache_backend, 'link_display_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginsAsOptions(?string $bundle = NULL, ?string $link_source = NULL): array {
    $options = $this->getPluginsAsOptionsByBundle($bundle);

    $definitions = $this->getDefinitions();

    foreach ($options as $plugin_id => $label) {
      if (!is_array($definitions[$plugin_id]['link_sources']) || empty($definitions[$plugin_id]['link_sources'])) {
        // If no link sources are defined on the plugin to restrict for, we
        // allow it.
        continue;
      }

      // If no link source was specified, or it's not in the list of supported
      // ones, skip the plugin.
      if (!$link_source || !in_array($link_source, $definitions[$plugin_id]['link_sources'])) {
        unset($options[$plugin_id]);
      }
    }

    return $options;
  }

}
