<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_internal_source\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event to alter the referenceable bundles in the internal source plugin.
 */
class InternalSourceBundlesEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_link_lists.internal_source_bundles_event';

  /**
   * The entity type ID of referenced bundles.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The entity type bundles that can be referenced in the plugin.
   *
   * @var array
   */
  protected $bundles;

  /**
   * InternalSourceBundlesEvent constructor.
   *
   * @param string $entityType
   *   An entity type ID.
   * @param array $bundles
   *   An array of bundle IDs.
   */
  public function __construct(string $entityType, array $bundles) {
    $this->entityType = $entityType;
    $this->bundles = $bundles;
  }

  /**
   * Returns entity type ID.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * Returns bundles of the entity type.
   *
   * @return array
   *   An array of entity type bundle IDs.
   */
  public function getBundles(): array {
    return $this->bundles;
  }

  /**
   * Sets the entity type bundles.
   *
   * @param array $bundles
   *   An array of entity bundle IDs.
   */
  public function setBundles(array $bundles): void {
    $this->bundles = $bundles;
  }

}
