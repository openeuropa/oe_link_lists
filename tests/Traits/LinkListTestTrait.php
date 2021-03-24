<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Traits;

use Behat\Mink\Element\NodeElement;
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

  /**
   * Checks if a select element contains the specified options.
   *
   * @param string $name
   *   The field name.
   * @param array $expected_options
   *   An array of expected options.
   */
  protected function assertFieldSelectOptions(string $name, array $expected_options): void {
    $select = $this->getSession()->getPage()->find('named', [
      'select',
      $name,
    ]);

    if (!$select) {
      $this->fail('Unable to find select ' . $name);
    }

    $options = $select->findAll('css', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getValue();
    });
    $options = array_filter($options);
    sort($options);
    sort($expected_options);
    $this->assertIdentical($options, $expected_options);
  }

}
