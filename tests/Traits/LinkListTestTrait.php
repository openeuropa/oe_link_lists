<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Traits;

use Drupal\oe_link_lists\Entity\LinkListInterface;

/**
 * Provides helper methods for testing link lists.
 */
trait LinkListTestTrait {

  /**
   * Returns a link list entity given its title.
   *
   * @param string $title
   *   The link list title.
   * @param bool $reset
   *   Whether to reset the link list entity cache. Defaults to FALSE.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface|null
   *   The first link list entity that matches the title. NULL if not found.
   */
  protected function getLinkListByTitle(string $title, bool $reset = FALSE): ?LinkListInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('link_list');
    if ($reset) {
      $storage->resetCache();
    }

    $entities = $storage->loadByProperties(['title' => $title]);

    if (empty($entities)) {
      return NULL;
    }

    return reset($entities);
  }

}
