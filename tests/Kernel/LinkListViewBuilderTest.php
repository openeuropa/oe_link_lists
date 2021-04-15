<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Functional;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the link list view builder.
 */
class LinkListViewBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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

    $mode_storage = $this->container->get('entity_type.manager')->getStorage('entity_view_mode');
    $display_storage = $this->container->get('entity_type.manager')->getStorage('entity_view_display');
    // Create a cacheable and an uncacheable view modes and displays for
    // link list entities.
    $view_modes = [
      'cacheable' => TRUE,
      'uncacheable' => FALSE,
    ];
    foreach ($view_modes as $mode => $cacheable) {
      $mode_storage->create([
        'id' => 'link_list.' . $mode,
        'targetEntityType' => 'link_list',
        'bundle' => 'dynamic',
        'status' => TRUE,
        'cache' => $cacheable,
      ])->save();
      $display_storage->create([
        'targetEntityType' => 'link_list',
        'bundle' => 'dynamic',
        'mode' => $mode,
        'status' => TRUE,
      ])->save();
    }
  }

  /**
   * Tests link list render cache handling.
   *
   * The class \Drupal\oe_link_lists\LinkListViewBuilder, compared to the core
   * entity view builder, allows to cache non-existing view modes.
   *
   * @see \Drupal\oe_link_lists\LinkListViewBuilder::isViewModeCacheable()
   */
  public function testLinkListViewBuilderCache() {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder('link_list');

    // Create a link list with only required values.
    $link_list = $storage->create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ]);
    $configuration = [
      'source' => [
        'plugin' => 'test_cache_metadata',
      ],
      'display' => [
        'plugin' => 'test_link_tag',
        'plugin_configuration' => ['link' => FALSE],
      ],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    // Render the entity with a non-existing view mode.
    $not_existing_view_mode = mb_strtolower($this->randomMachineName());
    $build = $view_builder->view($link_list, $not_existing_view_mode);
    // The cache keys should be present, so that the render array can be cached.
    $this->assertEquals([
      'entity_view',
      'link_list',
      (string) $link_list->id(),
      $not_existing_view_mode,
    ], $build['#cache']['keys']);

    // Verify that the default view mode is always cacheable.
    $build = $view_builder->view($link_list, 'default');
    $this->assertEquals([
      'entity_view',
      'link_list',
      (string) $link_list->id(),
      'default',
    ], $build['#cache']['keys']);

    // Verify that an existing view mode with cache enabled is correctly cached.
    $build = $view_builder->view($link_list, 'cacheable');
    $this->assertEquals([
      'entity_view',
      'link_list',
      (string) $link_list->id(),
      'cacheable',
    ], $build['#cache']['keys']);

    // Verify that an existing view mode with cache disabled does not have
    // cache keys, meaning that it will not be cached.
    $build = $view_builder->view($link_list, 'uncacheable');
    $this->assertArrayNotHasKey('keys', $build['#cache']);
  }

}
