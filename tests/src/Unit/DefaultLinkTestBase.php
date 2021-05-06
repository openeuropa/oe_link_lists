<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Unit;

use Drupal\oe_link_lists\LinkInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Abstract base class for tests about link classes.
 */
abstract class DefaultLinkTestBase extends UnitTestCase {

  /**
   * Returns an instance of link used for the test.
   *
   * @return \Drupal\oe_link_lists\LinkInterface
   *   A link instance.
   */
  abstract protected function getLinkInstance(): LinkInterface;

  /**
   * Tests that the link class throws an exception for unsupported operations.
   *
   * @param string $operation
   *   The access operation to check.
   *
   * @dataProvider unsupportedOperationsProvider
   * @covers ::access
   */
  public function testUnsupportedAccessOperation(string $operation): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Only the "view" permission is supported for links.');

    $link = $this->getLinkInstance();
    $link->access($operation);
  }

  /**
   * A data provider of unsupported access operations.
   *
   * @return array
   *   A list of unsupported operations.
   */
  public function unsupportedOperationsProvider(): array {
    return [
      ['create'],
      ['edit'],
      ['update'],
      ['delete'],
    ];
  }

}
