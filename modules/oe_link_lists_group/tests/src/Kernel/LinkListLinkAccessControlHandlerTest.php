<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_group\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\oe_link_lists\Traits\AssertAccessTrait;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;
use Drupal\oe_link_lists_group\Access\LinkListLinkAccessControlHandler;

/**
 * Test the group link list link access control handler delegation.
 */
class LinkListLinkAccessControlHandlerTest extends GroupKernelTestBase {

  use AssertAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'oe_link_lists_group',
    'oe_link_lists_manual_source',
    'oe_link_lists_test',
    'system',
    'user',
    'link',
    'node',
    'entity_reference_revisions',
    'inline_entity_form',
    'composite_reference',
    'field',
  ];

  /**
   * The access control handler.
   *
   * @var \Drupal\oe_link_lists_group\Access\LinkListLinkAccessControlHandler
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
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('link_list');
    $this->installEntitySchema('link_list_link');
    $this->installConfig([
      'oe_link_lists',
      'oe_link_lists_group',
      'oe_link_lists_manual_source',
      'composite_reference',
    ]);

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('link_list_link');

    // Create a UID 1 user to be able to create test users with particular
    // permissions in the tests.
    $this->drupalCreateUser();

    // Create bundles for tests.
    $link_list_link_type_storage = $this->container->get('entity_type.manager')->getStorage('link_list_link_type');
    $link_list_link_type_storage->create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();

    // Create a group type for testing.
    $groupType = $this->createGroupType([
      'id' => 'test_group',
      'creator_membership' => FALSE,
    ]);

    // Set up the group content plugins.
    $group_content_type_storage = $this->container->get('entity_type.manager')->getStorage('group_content_type');
    $group_content_type_storage->save($group_content_type_storage->createFromPlugin($groupType, 'group_link_list:manual'));

    $this->pluginManager->clearCachedPluginMaps();
  }

  /**
   * Tests that access is properly delegated to the parent link list.
   */
  public function testAccessDelegation(): void {
    // Create a link list.
    $link_list = $this->createLinkList([
      'bundle' => 'dynamic',
      'administrative_title' => $this->randomString(),
      'status' => TRUE,
    ]);

    // Create a link list link that belongs to the link list.
    $link_list_link = $this->createLinkListLink([
      'bundle' => 'test',
      'parent_type' => 'link_list',
      'parent_id' => $link_list->id(),
      'parent_field_name' => 'links',
      'status' => TRUE,
    ]);

    $scenarios = $this->accessDelegationDataProvider();

    foreach ($scenarios as $scenario => $test_data) {
      // Update the link list published status based on the scenario.
      $link_list->set('status', $test_data['link_list_published']);
      $link_list->save();

      // Update the link list link published status based on the scenario.
      $link_list_link->set('status', $test_data['link_list_link_published']);
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
   * Tests create access delegation via route match.
   */
  public function testCreateAccessDelegation(): void {
    // Create a link list.
    $link_list = $this->createLinkList([
      'bundle' => 'dynamic',
      'administrative_title' => $this->randomString(),
      'status' => TRUE,
    ]);

    // Create a custom access control handler with mocked route match.
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')
      ->willReturnCallback(function ($parameter_name) use ($link_list) {
        if ($parameter_name === 'link_list') {
          return $link_list;
        }
        return NULL;
      });

    $entity_type = $this->container->get('entity_type.manager')->getDefinition('link_list_link');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $access_handler = new LinkListLinkAccessControlHandler($entity_type, $entity_type_manager, $route_match);

    $user = $this->drupalCreateUser(['administer link_lists']);

    $this->assertAccessResult(
      AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags([
        'group_content_list:plugin:group_link_list:dynamic',
        'group_content_list:plugin:group_link_list:manual',
      ]),
      $access_handler->createAccess('test', $user, [], TRUE),
      'Admin should be able to create link list link when parent is in route.'
    );
  }

  /**
   * Tests create access via group when no parent link list in route.
   */
  public function testCreateAccessViaGroup(): void {
    // Test user with group permissions.
    $user_with_permissions = $this->drupalCreateUser();

    // Create mock group that allows permissions for this user.
    $group_allowed = $this->createMock(GroupInterface::class);
    $group_allowed->method('hasPermission')
      ->with('create group_link_list:manual entity', $user_with_permissions)
      ->willReturn(TRUE);

    // Create a custom access control handler with group in route match.
    $route_match_allowed = $this->createMock(RouteMatchInterface::class);
    $route_match_allowed->method('getParameter')
      ->willReturnCallback(function ($parameter_name) use ($group_allowed) {
        if ($parameter_name === 'link_list') {
          return NULL;
        }
        if ($parameter_name === 'group') {
          return $group_allowed;
        }
        return NULL;
      });

    $entity_type = $this->container->get('entity_type.manager')->getDefinition('link_list_link');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $access_handler_allowed = new LinkListLinkAccessControlHandler($entity_type, $entity_type_manager, $route_match_allowed);

    $this->assertAccessResult(
      AccessResult::allowed(),
      $access_handler_allowed->createAccess('test', $user_with_permissions, [], TRUE),
      'User with group permissions should be able to create link list link via group access.'
    );

    // Test user without group permissions.
    $user_without_permissions = $this->drupalCreateUser();

    // Create mock group that denies permissions for this user.
    $group_denied = $this->createMock(GroupInterface::class);
    $group_denied->method('hasPermission')
      ->with('create group_link_list:manual entity', $user_without_permissions)
      ->willReturn(FALSE);

    $route_match_denied = $this->createMock(RouteMatchInterface::class);
    $route_match_denied->method('getParameter')
      ->willReturnCallback(function ($parameter_name) use ($group_denied) {
        if ($parameter_name === 'link_list') {
          return NULL;
        }
        if ($parameter_name === 'group') {
          return $group_denied;
        }
        return NULL;
      });

    $access_handler_denied = new LinkListLinkAccessControlHandler($entity_type, $entity_type_manager, $route_match_denied);

    $this->assertAccessResult(
      AccessResult::forbidden('User does not have permission to create manual link lists in this group.'),
      $access_handler_denied->createAccess('test', $user_without_permissions, [], TRUE),
      'User without group permissions should not be able to create link list link via group access.'
    );
  }

  /**
   * Tests create access fallback to permissions when no parent in route.
   */
  public function testCreateAccessFallback(): void {
    // Create a custom access control handler with empty route match.
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')
      ->willReturn(NULL);

    $entity_type = $this->container->get('entity_type.manager')->getDefinition('link_list_link');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $access_handler = new LinkListLinkAccessControlHandler($entity_type, $entity_type_manager, $route_match);

    $user = $this->drupalCreateUser(['administer link list link entities']);

    $this->assertAccessResult(
      AccessResult::allowed()->addCacheContexts(['user.permissions']),
      $access_handler->createAccess('test', $user, [], TRUE),
      'Admin should be able to create link list link via fallback permissions.'
    );
  }

  /**
   * Tests access when parent link list doesn't exist.
   */
  public function testAccessWithoutParent(): void {
    // Create a link list link without a valid parent.
    $link_list_link = $this->createLinkListLink([
      'bundle' => 'test',
      'parent_type' => 'link_list',
      // Non-existent ID.
      'parent_id' => 999999,
      'parent_field_name' => 'links',
      'status' => TRUE,
    ]);

    $user = $this->drupalCreateUser([]);

    $result = $this->accessControlHandler->access($link_list_link, 'view', $user, TRUE);
    $this->assertAccessResult(
      AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['link_list_link:1']),
      $result,
      'Access should be neutral when parent link list does not exist.'
    );
  }

  /**
   * Data provider for testAccessDelegation().
   *
   * @return array
   *   The data sets to test.
   */
  protected function accessDelegationDataProvider(): array {
    return [
      'user with basic view access can view link list link' => [
        'permissions' => ['view link list'],
        'operation' => 'view',
        'link_list_published' => TRUE,
        'link_list_link_published' => TRUE,
        'expected_result' => AccessResult::allowed()
          ->addCacheContexts(['user.permissions'])
          ->addCacheTags([
            'group_content_list:plugin:group_link_list:dynamic',
            'group_content_list:plugin:group_link_list:manual',
            'link_list:1',
          ]),
      ],
      'user without link list access cannot view link list link' => [
        'permissions' => [],
        'operation' => 'view',
        'link_list_published' => TRUE,
        'link_list_link_published' => TRUE,
        'expected_result' => AccessResult::neutral()
          ->addCacheContexts(['user.permissions'])
          ->addCacheTags([
            'group_content_list:plugin:group_link_list:dynamic',
            'group_content_list:plugin:group_link_list:manual',
            'link_list:1',
          ]),
      ],
      'admin can access all operations' => [
        'permissions' => ['administer link_lists'],
        'operation' => 'view',
        'link_list_published' => TRUE,
        'link_list_link_published' => TRUE,
        'expected_result' => AccessResult::allowed()
          ->addCacheContexts(['user.permissions'])
          ->addCacheTags([
            'group_content_list:plugin:group_link_list:dynamic',
            'group_content_list:plugin:group_link_list:manual',
          ]),
      ],
    ];
  }

  /**
   * Create a link list entity.
   *
   * @param array $values
   *   Properties.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   Link list entity.
   */
  protected function createLinkList(array $values): LinkListInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->create($values);
    $link_list->save();
    return $link_list;
  }

  /**
   * Create a link list link entity.
   *
   * @param array $values
   *   Properties.
   *
   * @return \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface
   *   Link list link entity.
   */
  protected function createLinkListLink(array $values): LinkListLinkInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list_link');
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link_list_link */
    $link_list_link = $storage->create($values);
    $link_list_link->save();
    return $link_list_link;
  }

}
