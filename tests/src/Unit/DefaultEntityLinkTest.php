<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\LinkInterface;

/**
 * Tests the default entity link class.
 *
 * @group oe_link_lists
 * @coversDefaultClass \Drupal\oe_link_lists\DefaultEntityLink
 */
class DefaultEntityLinkTest extends DefaultLinkTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getLinkInstance(): LinkInterface {
    $url = $this->prophesize(Url::class)->reveal();
    return new DefaultEntityLink($url, 'Test', []);
  }

  /**
   * Tests the view access operation.
   *
   * @covers ::access
   */
  public function testViewAccessOperation(): void {
    $link = $this->getLinkInstance();

    // When no entity is set, the link is accessible.
    $this->assertTrue($link->access('view'));

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->access('view', NULL, TRUE)
      ->willReturn(AccessResult::neutral())
      ->shouldBeCalled();
    $link->setEntity($entity->reveal());
    $this->assertFalse($link->access('view'));
    $this->assertInstanceOf(AccessResultNeutral::class, $link->access('view', NULL, TRUE));

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->access('view', NULL, TRUE)
      ->willReturn(AccessResult::allowed())
      ->shouldBeCalled();
    $link->setEntity($entity->reveal());
    $this->assertTrue($link->access('view'));
    $this->assertInstanceOf(AccessResultAllowed::class, $link->access('view', NULL, TRUE));

    // Verify that the account is properly forwarded to the entity for access
    // checks.
    $anonymous = $this->prophesize(AccountInterface::class)->reveal();
    $editor = $this->prophesize(AccountInterface::class)->reveal();

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->access('view', $anonymous, TRUE)
      ->willReturn(AccessResult::neutral())
      ->shouldBeCalledTimes(2);
    $entity->access('view', $editor, TRUE)
      ->willReturn(AccessResult::allowed())
      ->shouldBeCalledTimes(2);

    $link->setEntity($entity->reveal());
    $this->assertFalse($link->access('view', $anonymous));
    $this->assertInstanceOf(AccessResultNeutral::class, $link->access('view', $anonymous, TRUE));
    $this->assertTrue($link->access('view', $editor));
    $this->assertInstanceOf(AccessResultAllowed::class, $link->access('view', $editor, TRUE));
  }

}
