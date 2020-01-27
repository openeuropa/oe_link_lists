<?php

namespace Drupal\Tests\oe_link_lists\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that link lists are properly cached.
 */
class LinkListViewBuilderTest extends KernelTestBase {

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Link list to test.
   *
   * @var \Drupal\oe_link_lists\Entity\LinkListInterface
   */
  protected $linkList;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'oe_link_lists',
    'oe_link_lists_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('link_list');
    $this->installConfig([
      'oe_link_lists',
      'system',
    ]);

    $this->storage = $this->container->get('entity_type.manager')->getStorage('link_list');

    // Create a block with only required values.
    $this->linkList = $this->storage->create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ]);
    $configuration = [
      'source' => [
        'plugin' => 'foo',
      ],
      'display' => [
        'plugin' => 'foo',
        'plugin_configuration' => ['link' => FALSE],
      ],
    ];
    $this->linkList->setConfiguration($configuration);
    $this->linkList->save();

    $this->container->get('cache.render')->deleteAll();

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Tests link list render cache handling.
   */
  public function testLinkListViewBuilderCache() {
    // Force a request via GET so we can test the render cache.
    $request = \Drupal::request();
    $request_method = $request->server->get('REQUEST_METHOD');
    $request->setMethod('GET');

    // Test that a cache entry is created.
    $build = $this->container->get('entity_type.manager')->getViewBuilder('link_list')->view($this->linkList, 'full');
    $cid_parts = array_merge($build['#cache']['keys'], \Drupal::service('cache_contexts_manager')->convertTokensToKeys([
      'languages:' . LanguageInterface::TYPE_INTERFACE,
      'theme',
      'user.permissions',
    ])->getKeys());
    $cid = implode(':', $cid_parts);
    $bin = $build['#cache']['bin'];

    $this->renderer->renderRoot($build);
    $this->assertTrue($this->container->get('cache.' . $bin)->get($cid), 'The link list render element has been cached.');

    // Re-save the block and check that the cache entry has been deleted.
    $this->linkList->save();
    $this->assertFalse($this->container->get('cache.' . $bin)->get($cid), 'The link list render cache entry has been cleared when the link list was saved.');

    // Rebuild the render array (creating a new cache entry in the process) and
    // delete the block to check the cache entry is deleted.
    unset($build['#printed']);
    $this->renderer->renderRoot($build);
    $this->assertTrue($this->container->get('cache.' . $bin)->get($cid), 'The link list render element has been cached.');
    $this->linkList->delete();
    $this->assertFalse($this->container->get('cache.' . $bin)->get($cid), 'The link list render cache entry has been cleared when the link list was deleted.');

    // Restore the previous request method.
    $request->setMethod($request_method);
  }

}
