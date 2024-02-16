<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * NoResultsBehaviour plugin manager.
 */
class NoResultsBehaviourPluginManager extends DefaultPluginManager implements NoResultsBehaviourPluginManagerInterface {

  use LinkListPluginManagerTrait;

  /**
   * Constructs NoResultsBehaviourPluginManager object.
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
      'Plugin/NoResultsBehaviour',
      $namespaces,
      $module_handler,
      'Drupal\oe_link_lists\NoResultsBehaviourInterface',
      'Drupal\oe_link_lists\Annotation\NoResultsBehaviour'
    );
    $this->alterInfo('no_results_behaviour_info');
    $this->setCacheBackend($cache_backend, 'no_results_behaviour_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginsAsOptions(string $bundle = NULL): array {
    return $this->getPluginsAsOptionsByBundle($bundle);
  }

}
