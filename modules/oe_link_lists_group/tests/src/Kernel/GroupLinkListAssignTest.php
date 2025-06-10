<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_group\Kernel;

use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Tests assigning behaviour.
 */
class GroupLinkListAssignTest extends GroupKernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group we will use to test methods on.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * The link list group relationship type.
   *
   * @var \Drupal\group\Entity\GroupRelationshipType
   */
  protected $groupRelationshipType;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'text',
    'system',
    'node',
    'link',
    'gnode',
    'inline_entity_form',
    'oe_link_lists',
    'oe_link_lists_group',
    'oe_link_lists_group_test',
    'oe_link_lists_local',
    'oe_link_lists_internal_source',
    'oe_link_lists_manual_source',
    'oe_link_lists_test',
    'entity_reference_revisions',
    'composite_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('link_list');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'field',
      'node',
      'system',
      'entity_reference_revisions',
      'inline_entity_form',
      'oe_link_lists',
      'oe_link_lists_local',
      'oe_link_lists_internal_source',
      'oe_link_lists_manual_source',
      'oe_link_lists_group',
      'oe_link_lists_group_test',
      'composite_reference',
    ]);

    $groupType = $this->createGroupType([
      'id' => 'link_list_test',
      'creator_membership' => FALSE,
    ]);

    $group_content_type_storage = $this->entityTypeManager->getStorage('group_content_type');
    $group_content_type_storage->save($group_content_type_storage->createFromPlugin($groupType, 'group_link_list:dynamic'));
    $group_content_type_storage->save($group_content_type_storage->createFromPlugin($groupType, 'group_node:test_link_list_group'));

    $this->pluginManager->clearCachedPluginMaps();

    $this->groupRelationshipType = $group_content_type_storage->load($group_content_type_storage->getRelationshipTypeId($groupType->id(), 'group_link_list:dynamic'));

    $this->group = $this->createGroup(['type' => $groupType->id()]);
  }

  /**
   * Test that link list is added to group.
   */
  public function testLinkListAdding(): void {
    $link_list_dynamic = $this->createLinkList([
      'bundle' => 'dynamic',
      'title' => $this->randomString(),
      'administrative_title' => $this->randomString(),
    ]);
    $link_list_dynamic->save();
    $link_list_manual = $this->createLinkList([
      'bundle' => 'manual',
      'title' => $this->randomString(),
      'administrative_title' => $this->randomString(),
    ]);
    $link_list_manual->save();

    // Create a node.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'title' => 'Link list node',
      'type' => 'test_link_list_group',
      'field_link_list' => [
        $link_list_dynamic,
        $link_list_manual,
      ],
    ]);
    $node->enforceIsNew();
    $node->save();

    $this->group->addRelationship($node, 'group_node:test_link_list_group');

    $this->assertEquals(1, count($this->group->getRelationships('group_link_list:dynamic')));

    // Manual link lists are not enabled as group relationship.
    $this->assertEquals(0, count($this->group->getRelationships('group_link_list:manual')));
  }

  /**
   * Create a link list item.
   *
   * @param array $values
   *   Properties.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface
   *   Link list item.
   */
  protected function createLinkList(array $values): LinkListInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->create($values);
    $configuration = [
      'source' => [
        'plugin' => 'internal',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'test_link_list_group',
        ],
      ],
      'display' => [
        'plugin' => 'test_configurable_title',
      ],
    ];
    $link_list->setConfiguration($configuration);
    return $link_list;
  }

}
