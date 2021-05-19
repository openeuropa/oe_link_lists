<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Unit;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\LinkInterface;

/**
 * Tests the default link class.
 *
 * @group oe_link_lists
 * @coversDefaultClass \Drupal\oe_link_lists\DefaultLink
 */
class DefaultLinkTest extends DefaultLinkTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getLinkInstance(): LinkInterface {
    $url = $this->prophesize(Url::class);
    return new DefaultLink($url->reveal(), 'Test', []);
  }

  /**
   * Tests the view access operation.
   *
   * @covers ::access
   */
  public function testViewAccessOperation(): void {
    $link = $this->getLinkInstance();
    $this->assertTrue($link->access('view'));
    $this->assertInstanceOf(AccessResultAllowed::class, $link->access('view', NULL, TRUE));
  }

}
