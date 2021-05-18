<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests link list routes access.
 */
class LinkListRouteAccessTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'oe_link_lists',
    'system',
    'user',
  ];

  /**
   * Test access for collection route.
   */
  public function testCollectionRouteAccess() {
    $access_manager = $this->container->get('access_manager');

    // Administrator.
    $user = $this->drupalCreateUser(['administer link_lists']);
    $actual = $access_manager->checkNamedRoute('entity.link_list.collection', [], $user, TRUE);
    $this->assertTrue($actual->isAllowed());

    // User with access link list overview permission.
    $user = $this->drupalCreateUser(['access link list overview']);
    $actual = $access_manager->checkNamedRoute('entity.link_list.collection', [], $user, TRUE);
    $this->assertTrue($actual->isAllowed());

    // User without permissions.
    $user = $this->drupalCreateUser([]);
    $actual = $access_manager->checkNamedRoute('entity.link_list.collection', [], $user, TRUE);
    $this->assertTrue($actual->isNeutral());
  }

}
