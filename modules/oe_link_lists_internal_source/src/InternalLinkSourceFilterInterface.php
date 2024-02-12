<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for internal link source filter plugins.
 */
interface InternalLinkSourceFilterInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Returns if the plugin can be used given a certain configuration.
   *
   * @param string $entity_type
   *   The entity type selected in the source plugin.
   * @param string $bundle
   *   The bundle selected in the source plugin.
   *
   * @return bool
   *   True if the plugin is applicable, false otherwise.
   */
  public function isApplicable(string $entity_type, string $bundle): bool;

  /**
   * Applies the filter to the given query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query of the internal link source plugin.
   * @param array $context
   *   An array containing information about the internal source plugin.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The refinable cacheability metadata for the current plugin.
   */
  public function apply(QueryInterface $query, array $context, RefinableCacheableDependencyInterface $cacheability): void;

}
