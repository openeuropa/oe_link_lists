<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_internal_source\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event to alter the referenceable entity types in the internal source plugin.
 */
class InternalSourceEntityTypesEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_link_lists.internal_source_entity_types_event';

  /**
   * The entity type IDs that can be referenced in the plugin.
   *
   * @var array
   */
  protected $entityTypes;

  /**
   * InternalSourceEntityTypesEvent constructor.
   *
   * @param array $entityTypes
   *   An array of entity type IDs.
   */
  public function __construct(array $entityTypes) {
    $this->entityTypes = $entityTypes;
  }

  /**
   * Returns the entity types.
   *
   * @return array
   *   An array of entity type IDS.
   */
  public function getEntityTypes(): array {
    return $this->entityTypes;
  }

  /**
   * Sets the entity types.
   *
   * @param array $entityTypes
   *   An array of entity type IDs.
   */
  public function setEntityTypes(array $entityTypes): void {
    $this->entityTypes = $entityTypes;
  }

}
