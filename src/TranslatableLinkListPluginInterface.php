<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

/**
 * Implemented by plugins that provide translatable configuration.
 */
interface TranslatableLinkListPluginInterface {

  /**
   * Returns the translatable parents.
   *
   * Each set of parents is an array of they array keys needed to drill down
   * into the configuration schema.
   *
   * @return array
   *   An array of the parents of the configuration values that can be
   *   translated.
   */
  public function getTranslatableParents(): array;

}
