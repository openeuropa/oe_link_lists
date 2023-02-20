<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\oe_link_lists\Entity\LinkListType;
use Drupal\Tests\oe_link_lists\Traits\AssertAccessTrait;

/**
 * Test the link list type access control handler.
 */
class LinkListTypeAccessControlHandlerTest extends EntityKernelTestBase {

  use AssertAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'system',
    'user',
  ];

  /**
   * The access control handler.
   *
   * @var \Drupal\oe_link_lists\LinkListAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('oe_link_lists');

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('link_list_type');

    // Create a UID 1 user to be able to create test users with particular
    // permissions in the tests.
    $this->drupalCreateUser();
  }

  /**
   * Ensures link list type create access is properly working.
   */
  public function testCreateAccess(): void {
    $scenarios = $this->createAccessDataProvider();
    foreach ($scenarios as $scenario => $test_data) {
      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->createAccess(NULL, $user, [], TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }

    // Check create access for admin user.
    $admin = $this->drupalCreateUser([], '', TRUE);
    $this->assertAccessResult(
      AccessResult::allowed()->addCacheContexts(['user.permissions']),
      $this->accessControlHandler->createAccess(NULL, $admin, [], TRUE),
      sprintf('Failed asserting create access for administrator user.')
    );
  }

  /**
   * Ensures link list type access is properly working.
   */
  public function testAccess(): void {
    $entity = LinkListType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $entity->save();
    $scenarios = $this->accessDataProvider();
    foreach ($scenarios as $scenario => $test_data) {
      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->access($entity, $test_data['operation'], $user, TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }

    // Check access for admin user.
    $admin = $this->drupalCreateUser([], '', TRUE);
    $this->assertAccessResult(
      AccessResult::allowed()->addCacheContexts(['user.permissions']),
      $this->accessControlHandler->access($entity, 'view', $admin, TRUE),
      sprintf('Failed asserting view access for administrator user.')
    );
    $this->assertAccessResult(
      AccessResult::allowed()->addCacheContexts(['user.permissions']),
      $this->accessControlHandler->access($entity, 'delete', $admin, TRUE),
      sprintf('Failed asserting delete access for administrator user.')
    );
  }

  /**
   * Data provider for testCreateAccess().
   *
   * This method is not declared as a real PHPUnit data provider to speed up
   * test execution.
   *
   * @return array
   *   The data sets to test.
   */
  protected function createAccessDataProvider(): array {
    return [
      'user without permissions' => [
        'permissions' => [],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view access' => [
        'permissions' => [
          'view link list',
          'view unpublished link list',
        ],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'admin' => [
        'permissions' => ['administer link list types'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
    ];
  }

  /**
   * Data provider for testAccess().
   *
   * This method is not declared as a real PHPUnit data provider to speed up
   * test execution.
   *
   * @return array
   *   The data sets to test.
   */
  protected function accessDataProvider(): array {
    return [
      'user without permissions / view' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view permission / view' => [
        'permissions' => ['view link list'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user without permissions / view label' => [
        'permissions' => [],
        'operation' => 'view label',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view permission / view label' => [
        'permissions' => ['view link list'],
        'operation' => 'view label',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user without permissions / delete' => [
        'permissions' => [],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'admin / view' => [
        'permissions' => ['administer link list types'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'admin / delete' => [
        'permissions' => ['administer link list types'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
    ];
  }

}
