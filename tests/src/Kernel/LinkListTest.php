<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
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
        'plugin' => 'bar',
      ],
      'display' => [
        'plugin' => 'bar',
        'plugin_configuration' => ['link' => FALSE],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();

    // Assert the display plugin preSave was called.
    $this->assertEquals($link_list->getRevisionLogMessage(), 'Bar presave was called.');

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
      'bar_test_tag_list',
      'link_list:1',
      'link_list_view',
    ], $build['#cache']['tags']);
    $this->assertEquals(1800, $build['#cache']['max-age']);
    // The renderer service adds required cache contexts to render arrays, so
    // we just assert the presence of the context added by the source plugin.
    $this->assertContains('user.is_super_user', $build['#cache']['contexts']);
  }

}
