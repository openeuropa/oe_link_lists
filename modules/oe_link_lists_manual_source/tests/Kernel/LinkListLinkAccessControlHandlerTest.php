<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_manual_source\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\oe_link_lists\Traits\AssertAccessTrait;

/**
 * Test the link list link access control handler.
 */
class LinkListLinkAccessControlHandlerTest extends EntityKernelTestBase {

  use AssertAccessTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'oe_link_lists',
    'oe_link_lists_manual_source',
    'system',
    'user',
    'link',
    'node',
    'entity_reference_revisions',
    'inline_entity_form',
    'field',
  ];

  /**
   * The access control handler.
   *
   * @var \Drupal\oe_link_lists_manual_source\LinkListLinkAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('link_list');
    $this->installEntitySchema('link_list_link');
    $this->installConfig('oe_link_lists');
    $this->installConfig('oe_link_lists_manual_source');

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('link_list_link');

    // Create a UID 1 user to be able to create test users with particular
    // permissions in the tests.
    $this->drupalCreateUser();

    // Create a bundle for tests.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('link_list_link_type');
    $type_storage->create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
  }

  /**
   * Ensures link list link access is properly working.
   */
  public function testAccess(): void {
    $scenarios = $this->accessDataProvider();
    $link_list_link_storage = $this->entityTypeManager->getStorage('link_list_link');
    $values = [
      'bundle' => 'test',
      'administrative_title' => $this->randomString(),
    ];

    // Create a link list link.
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLink $link_list_link */
    $link_list_link = $link_list_link_storage->create($values);
    $link_list_link->save();

    foreach ($scenarios as $scenario => $test_data) {
      // Update the published status based on the scenario.
      $link_list_link->setPublished($test_data['published']);
      $link_list_link->save();

      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->access($link_list_link, $test_data['operation'], $user, TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }
  }

  /**
   * Ensures link list link create access is properly working.
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
      'user without permissions / published link list link' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => TRUE,
      ],
      'user without permissions / unpublished link list link' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => FALSE,
      ],
      'admin view' => [
        'permissions' => ['administer link list link entities'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin view unpublished' => [
        'permissions' => ['administer link list link entities'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => FALSE,
      ],
      'admin update' => [
        'permissions' => ['administer link list link entities'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin delete' => [
        'permissions' => ['administer link list link entities'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with view access / published link list link' => [
        'permissions' => ['view link list link'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => TRUE,
      ],
      'user with view access / unpublished link list link' => [
        'permissions' => ['view link list link'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => FALSE,
      ],
      'user with view unpublished access / published link list link' => [
        'permissions' => ['view unpublished link list link'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => TRUE,
      ],
      'user with view unpublished access / unpublished link list link' => [
        'permissions' => ['view unpublished link list link'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => FALSE,
      ],
      'user with create, update, delete access / published link list link' => [
        'permissions' => [
          'create test link list link',
          'edit test link list link',
          'delete test link list link',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => TRUE,
      ],
      'user with create, update, delete access / unpublished link list link' => [
        'permissions' => [
          'create test link list link',
          'edit test link list link',
          'delete test link list link',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
        'published' => FALSE,
      ],
      'user with update access' => [
        'permissions' => ['edit test link list link'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with update access on different bundle' => [
        'permissions' => ['edit internal_content link list link'],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, delete access' => [
        'permissions' => [
          'create test link list link',
          'view link list link',
          'view unpublished link list link',
          'delete test link list link',
        ],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access' => [
        'permissions' => ['delete test link list link'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access on different bundle' => [
        'permissions' => ['delete external link list link'],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, update access' => [
        'permissions' => [
          'create test link list link',
          'view link list link',
          'view unpublished link list link',
          'edit test link list link',
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
        'permissions' => ['administer link list link entities'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with view access' => [
        'permissions' => ['view link list link'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view, update and delete access' => [
        'permissions' => [
          'view link list link',
          'view unpublished link list link',
          'edit test link list link',
          'delete test link list link',
        ],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with create access' => [
        'permissions' => ['create test link list link'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with create access on different bundle' => [
        'permissions' => ['create external link list link'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
    ];
  }

}
