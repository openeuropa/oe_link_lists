<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\oe_link_lists\LinkDisplayInterface;
use Drupal\oe_link_lists\LinkDisplayPluginManagerInterface;
use Drupal\oe_link_lists\LinkSourceInterface;
use Drupal\oe_link_lists\LinkSourcePluginManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the Link list entity.
 */
class LinkListTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'oe_link_lists',
    'oe_link_lists_test',
    'user',
    'system',
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
    $this->installEntitySchema('user');
    $this->installEntitySchema('link_list');
    $this->installConfig([
      'oe_link_lists',
      'system',
    ]);

    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('link_list');
  }

  /**
   * Tests Link list entities.
   */
  public function testLinkList(): void {
    // Create a link list.
    $link_list_storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $values = [
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ];
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->create($values);
    $link_list->save();

    $link_list = $link_list_storage->load($link_list->id());
    $this->assertEquals('Link list 1', $link_list->getAdministrativeTitle());
    $this->assertEquals('My link list', $link_list->getTitle());
  }

  /**
   * Tests that we have a block derivative for each link list.
   */
  public function testBlockDerivatives(): void {
    $link_list_storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $values = [
      [
        'bundle' => 'dynamic',
        'title' => 'First list',
        'administrative_title' => 'Admin 1',
      ],
      [
        'bundle' => 'dynamic',
        'title' => 'Second list',
        'administrative_title' => 'Admin 2',
      ],
    ];

    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $this->container->get('plugin.manager.block');

    foreach ($values as $value) {
      $link_list = $link_list_storage->create($value);
      $link_list->save();

      $uuid = $link_list->uuid();
      $definition = $block_manager->getDefinition("oe_link_list_block:$uuid");
      $this->assertEquals($definition['admin_label'], $value['administrative_title']);

      /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
      $plugin = $block_manager->createInstance("oe_link_list_block:$uuid");
      $build = $plugin->build();
      $this->assertEquals('full', $build['#view_mode']);
      $this->assertTrue(isset($build['#link_list']));
    }

    // Make sure the block checks the link list permissions.
    // User with view access.
    $user = $this->drupalCreateUser(['view link list']);
    $expected = AccessResult::allowed()->addCacheContexts(['user.permissions']);
    $actual = $this->accessControlHandler->access($build['#link_list'], 'view', $user, TRUE);
    $this->assertEquals($expected->isAllowed(), $actual->isAllowed());

    // User without permissions.
    $user = $this->drupalCreateUser([]);
    $expected = AccessResult::neutral()->addCacheContexts(['user.permissions']);
    $actual = $this->accessControlHandler->access($build['#link_list'], 'view', $user, TRUE);
    $this->assertEquals($expected->isNeutral(), $actual->isNeutral());
  }

  /**
   * Tests that link lists are rendered by the selected display plugin.
   */
  public function testRendering(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->create([
      'bundle' => 'dynamic',
      'title' => 'Test',
      'administrative_title' => 'Test',
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'test_cache_metadata',
      ],
      'display' => [
        'plugin' => 'test_configurable_title',
        'plugin_configuration' => ['link' => FALSE],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('link_list');
    $build = $builder->view($link_list);
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $crawler = new Crawler($html);
    $items = $crawler->filter('ul li');
    $this->assertCount(2, $items);
    $this->assertEquals('Example', $items->first()->text());
    $this->assertEquals('European Commission', $items->eq(1)->text());

    // Verify that the proper cacheability metadata has been added to the
    // render array.
    $this->assertEquals([
      'bar_test_tag:1',
      'bar_test_tag:2',
      'link_list:1',
      'link_list_view',
      'test_cache_metadata_tag',
    ], $build['#cache']['tags']);
    $this->assertEquals(1800, $build['#cache']['max-age']);
    // The renderer service adds required cache contexts to render arrays, so
    // we just assert the presence of the context added by the source plugin.
    $this->assertContains('user.is_super_user', $build['#cache']['contexts']);

    // Test that the no results behaviour plugins render correctly.
    $configuration['source']['plugin'] = 'test_empty_collection_with_cache';
    $configuration['no_results_behaviour']['plugin'] = 'hide_list';
    $configuration['no_results_behaviour']['plugin_configuration'] = [];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('link_list');
    $build = $builder->view($link_list);
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertEquals("", $html);
    $this->assertEquals([
      'config:user.role.anonymous',
      'link_list:1',
      'link_list_view',
      'test_cache_metadata_tag',
      'user:0',
    ], $build['#cache']['tags']);

    $configuration['no_results_behaviour']['plugin'] = 'text_message';
    $configuration['no_results_behaviour']['plugin_configuration'] = [
      'text' => 'the no results text',
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $builder = $this->container->get('entity_type.manager')->getViewBuilder('link_list');
    $build = $builder->view($link_list);
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $this->assertEquals('the no results text', $html);
    $this->assertEquals([
      'config:user.role.anonymous',
      'link_list:1',
      'link_list_view',
      'test_cache_metadata_tag',
      'user:0',
    ], $build['#cache']['tags']);
  }

  /**
   * Tests that the source and display plugin presave methods are invoked.
   */
  public function testPluginPreSave(): void {
    $link_list = $this->container->get('entity_type.manager')
      ->getStorage('link_list')
      ->create([
        'bundle' => 'dynamic',
        'title' => $this->randomString(),
        'administrative_title' => $this->randomString(),
        'configuration' => [
          'source' => [
            'plugin' => 'mocked_source',
            'plugin_configuration' => [],
          ],
          'display' => [
            'plugin' => 'mocked_display',
            'plugin_configuration' => [
              'random_config' => 'random_value',
            ],
          ],
        ],
      ]);

    $mocked_source = $this->createMock(LinkSourceInterface::class);
    $mocked_source
      ->expects($this->once())
      ->method('preSave')
      ->with($link_list);

    $mocked_source_manager = $this->createMock(LinkSourcePluginManagerInterface::class);
    $mocked_source_manager
      ->expects($this->once())
      ->method('createInstance')
      ->with('mocked_source', [])
      ->willReturn($mocked_source);

    $mocked_display = $this->createMock(LinkDisplayInterface::class);
    $mocked_display
      ->expects($this->once())
      ->method('preSave')
      ->with($link_list);

    $mocked_display_manager = $this->createMock(LinkDisplayPluginManagerInterface::class);
    $mocked_display_manager
      ->expects($this->once())
      ->method('createInstance')
      ->with('mocked_display', ['random_config' => 'random_value'])
      ->willReturn($mocked_display);

    $this->container->set('plugin.manager.oe_link_lists.link_source', $mocked_source_manager);
    $this->container->set('plugin.manager.oe_link_lists.link_display', $mocked_display_manager);

    $link_list->save();
  }

}
