<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\oe_link_lists\Traits\AssertAccessTrait;

/**
 * Test the link list access control handler.
 */
class LinkListAccessControlHandlerTest extends EntityKernelTestBase {

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

    $this->installEntitySchema('link_list');
    $this->installConfig('oe_link_lists');

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('link_list');

    // Create a UID 1 user to be able to create test users with particular
    // permissions in the tests.
    $this->drupalCreateUser();

    // Create a bundle for tests.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('link_list_type');
    $type_storage->create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
  }

  /**
   * Ensures link list access is properly working.
   */
  public function testAccess(): void {
    $scenarios = $this->accessDataProvider();
    $link_list_storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $values = [
      'bundle' => 'test',
      'administrative_title' => $this->randomString(),
    ];

    // Create a link list.
    /** @var \Drupal\oe_link_lists\Entity\LinkList $link_list */
    $link_list = $link_list_storage->create($values);
    $link_list->save();

    foreach ($scenarios as $scenario => $test_data) {
      // Update the published status based on the scenario.
      $link_list->set('status', $test_data['published']);
      $link_list->save();

      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->access($link_list, $test_data['operation'], $user, TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }
  }

  /**
   * Ensures link list create access is properly working.
   */
  public function testCreateAccess(): void {
    $scenarios = $this->createAccessDataProvider();
    foreach ($scenarios as $scenario => $test_data) {
      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->createAccess('test', $user, [], TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }
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
      'user without permissions / published link list' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => TRUE,
      ],
      'user without permissions / unpublished link list' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => FALSE,
      ],
      'admin view' => [
        'permissions' => ['administer link_lists'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin view unpublished' => [
        'permissions' => ['administer link_lists'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => FALSE,
      ],
      'admin update' => [
        'permissions' => ['administer link_lists'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin delete' => [
        'permissions' => ['administer link_lists'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with view access / published link list' => [
        'permissions' => ['view link list'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => TRUE,
      ],
      'user with view access / unpublished link list' => [
        'permissions' => ['view link list'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => FALSE,
      ],
      'user with view unpublished access / published link list' => [
        'permissions' => ['view unpublished link list'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => TRUE,
      ],
      'user with view unpublished access / unpublished link list' => [
        'permissions' => ['view unpublished link list'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => FALSE,
      ],
      'user with create, update, delete access / published link list' => [
        'permissions' => [
          'create test link list',
          'edit test link list',
          'delete test link list',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => TRUE,
      ],
      'user with create, update, delete access / unpublished link list' => [
        'permissions' => [
          'create test link list',
          'edit test link list',
          'delete test link list',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list:1']),
        'published' => FALSE,
      ],
      'user with update access' => [
        'permissions' => ['edit test link list'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with update access on different bundle' => [
        'permissions' => ['edit dynamic link list'],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, delete access' => [
        'permissions' => [
          'create test link list',
          'view link list',
          'view unpublished link list',
          'delete test link list',
        ],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access' => [
        'permissions' => ['delete test link list'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access on different bundle' => [
        'permissions' => ['delete dynamic link list'],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, update access' => [
        'permissions' => [
          'create test link list',
          'view link list',
          'view unpublished link list',
          'edit test link list',
        ],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
    ];
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
      'admin' => [
        'permissions' => ['administer link_lists'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with view access' => [
        'permissions' => ['view link list'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view, update and delete access' => [
        'permissions' => [
          'view link list',
          'view unpublished link list',
          'edit test link list',
          'delete test link list',
        ],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with create access' => [
        'permissions' => ['create test link list'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with create access on different bundle' => [
        'permissions' => ['create dynamic link list'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
    ];
  }

}
