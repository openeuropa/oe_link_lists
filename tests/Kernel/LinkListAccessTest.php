<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that proper access checks are run on link list rendering.
 *
 * @group oe_link_lists
 */
class LinkListAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'oe_link_lists',
    'oe_link_lists_internal_source',
    'oe_link_lists_test',
    'entity_reference_revisions',
    'field',
    'node',
    'text',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('link_list');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'node',
      'system',
      'entity_reference_revisions',
      'oe_link_lists',
    ]);

    $node_type = NodeType::create([
      'type' => 'page',
      'id' => 'page',
    ]);
    $node_type->save();
  }

  /**
   * Tests that access checks are executed on link rendering.
   */
  public function testLinkAccess(): void {
    // Create a published node.
    $published = Node::create(['title' => 'Published', 'type' => 'page']);
    $published->setPublished()->save();

    // An unpublished one.
    $unpublished_entity = Node::create(['title' => 'Unpublished', 'type' => 'page']);
    $unpublished_entity->setUnpublished()->save();

    // A node with a published revision and a pending unpublished revision.
    $pending_unpublished = Node::create(['title' => 'Published revision', 'type' => 'page']);
    $pending_unpublished->setPublished()->save();
    // Create the pending revision.
    $pending_unpublished->setTitle('Unpublished revision');
    $pending_unpublished->setNewRevision();
    $pending_unpublished->isDefaultRevision(FALSE);
    $pending_unpublished->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->create([
      'bundle' => 'dynamic',
      'title' => $this->randomString(),
      'administrative_title' => $this->randomString(),
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'internal',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'page',
        ],
      ],
      'display' => [
        'plugin' => 'bar',
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('link_list');

    $build = $builder->view($link_list);
    $renderer = $this->container->get('renderer');

    // The current user is anonymous. No links should be rendered as they have
    // no permission to access nodes.
    $html = (string) $renderer->renderRoot($build);
    $this->assertEquals('', $html);

    // Verify that the access checks cacheability metadata is added to the
    // render array. Since the user.permissions is a required cache context,
    // and our custom access tags are not added if the user has no access
    // to nodes, we assert the presence of said context into the inner render
    // array.
    $this->assertContains('user.permissions', $build['entity']['#cache']['contexts']);

    // Create a user that can access content.
    $this->setUpCurrentUser([], ['access content']);
    $build = $builder->view($link_list);
    $html = (string) $renderer->renderRoot($build);
    // Only the published nodes are rendered.
    $this->assertEquals('<ul><li><a href="/node/1" hreflang="en">Published</a></li><li><a href="/node/3" hreflang="en">Published revision</a></li></ul>', $html);

    // Cacheability information added during access checks is correctly appended
    // to the render array.
    // @see oe_link_lists_test_node_access()
    $this->assertContains('oe_link_list_test_access_tag:1', $build['#cache']['tags']);
    $this->assertContains('oe_link_list_test_access_tag:2', $build['#cache']['tags']);
    $this->assertContains('oe_link_list_test_access_tag:3', $build['#cache']['tags']);

    // Create a user that can edit all content.
    $editor = $this->createUser(['bypass node access']);
    $this->setCurrentUser($editor);
    $build = $builder->view($link_list);
    $html = (string) $renderer->renderRoot($build);
    // All the nodes, even the unpublished ones, are rendered.
    $this->assertEquals('<ul><li><a href="/node/1" hreflang="en">Published</a></li><li><a href="/node/2" hreflang="en">Unpublished</a></li><li><a href="/node/3" hreflang="en">Published revision</a></li></ul>', $html);
  }

}
