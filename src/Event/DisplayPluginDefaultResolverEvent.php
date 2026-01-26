<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched to resolve the default display plugin.
 */
final class DisplayPluginDefaultResolverEvent extends Event {

  /**
   * Event name used by the Symfony EventDispatcher.
   */
  public const NAME = 'oe_link_lists.event.display_plugin_default_resolver';

  /**
   * The resolved default plugin ID.
   */
  private ?string $defaultPluginId = NULL;

  /**
   * Event Constructor.
   *
   * @param string[] $available_plugin_ids
   *   List of available display plugin IDs.
   */
  public function __construct(
    private readonly array $available_plugin_ids,
  ) {}

  /**
   * Returns all available display plugin IDs.
   *
   * @return string[]
   *   Array of available plugin IDs.
   */
  public function getAvailablePluginIds(): array {
    return $this->available_plugin_ids;
  }

  /**
   * Sets the default display plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID to be used as default.
   */
  public function setDefaultPluginId(string $plugin_id): void {
    $this->defaultPluginId = $plugin_id;
  }

  /**
   * Returns the resolved default display plugin ID.
   *
   * @return string|null
   *   The selected plugin ID or NULL if none was set.
   */
  public function getDefaultPluginId(): ?string {
    return $this->defaultPluginId;
  }

}
