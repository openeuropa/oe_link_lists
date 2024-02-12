<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_internal_source\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestNoBundle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the internal link source plugin.
 *
 * @group oe_link_lists
 * @coversDefaultClass \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource
 */
class InternalLinkSourcePluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'oe_link_lists',
    'oe_link_lists_internal_source',
    'oe_link_lists_internal_source_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_no_bundle');
    // Create two bundles for the entity_test entity type.
    entity_test_create_bundle('foo');
    entity_test_create_bundle('bar');
  }

  /**
   * Tests the getLinks() method.
   *
   * @covers ::getLinks
   */
  public function testGetLinks(): void {
    // Create some test entities.
    $test_entities_by_bundle = [];
    $test_entities_by_bundle_and_first_letter = [];
    foreach ($this->getTestEntities() as $entity_values) {
      $entity = EntityTest::create($entity_values);
      $entity->save();

      // Group the entities to allow easier testing.
      $test_entities_by_bundle[$entity->bundle()][$entity->id()] = $entity->label();
      $test_entities_by_bundle_and_first_letter[$entity->bundle()][substr($entity->label(), 0, 1)][$entity->id()] = $entity->label();
    }

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('internal');

    // Test a plugin without configuration.
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // Test partial configuration.
    $partial_configurations = [
      'no bundle' => ['entity_type' => 'entity_test', 'bundle' => ''],
      'no entity type' => ['entity_type' => '', 'bundle' => 'foo'],
    ];
    foreach ($partial_configurations as $case => $configuration) {
      $plugin->setConfiguration($configuration);
      $this->assertEquals([], $plugin->getLinks()->toArray(), "Invalid referenced entities for $case case.");
    }

    // If a non existing entity type is passed, the plugin should just return
    // an empty list.
    $plugin->setConfiguration([
      'entity_type' => 'non_existing_type',
      'bundle' => 'page',
    ]);
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // An empty list is returned if the bundle doesn't exist.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // Test that only the entities of the specified bundle are returned.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ]);
    $this->assertEquals($test_entities_by_bundle['foo'], $this->extractEntityNames($plugin->getLinks()->toArray()));
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'bar',
    ]);
    $this->assertEquals($test_entities_by_bundle['bar'], $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Test that the limit is applied to the results.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ]);
    $this->assertEquals(
      array_slice($test_entities_by_bundle['foo'], 0, 2, TRUE),
      $this->extractEntityNames($plugin->getLinks(2)->toArray())
    );

    // Test that the offset and limit are applied to the results.
    $this->assertEquals(
      array_slice($test_entities_by_bundle['foo'], 2, 2, TRUE),
      $this->extractEntityNames($plugin->getLinks(2, 2)->toArray())
    );

    // Test non bundleable entities.
    $test_entity = EntityTestNoBundle::create(['name' => $this->randomString()]);
    $test_entity->save();
    $plugin->setConfiguration([
      'entity_type' => 'entity_test_no_bundle',
      'bundle' => 'entity_test_no_bundle',
    ]);
    $this->assertEquals([
      $test_entity->id() => $test_entity->label(),
    ], $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Test that filters are applied correctly.
    // Only 3 entity_test entities of bundle foo start with the letter A.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [
        'first_letter' => [
          'first_letter' => 'A',
        ],
      ],
    ]);
    $this->assertCount(3, $plugin->getLinks()->toArray());
    $this->assertEquals(
      $test_entities_by_bundle_and_first_letter['foo']['A'],
      $this->extractEntityNames($plugin->getLinks()->toArray())
    );

    // Only 1 entity_test entity of bundle foo starts with the letter B.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [
        'first_letter' => [
          'first_letter' => 'B',
        ],
      ],
    ]);
    $this->assertCount(1, $plugin->getLinks()->toArray());
    $this->assertEquals(
      $test_entities_by_bundle_and_first_letter['foo']['B'],
      $this->extractEntityNames($plugin->getLinks()->toArray())
    );

    // No entity_test entities of bundle bar start with the letter A.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'bar',
      'filters' => [
        'first_letter' => [
          'first_letter' => 'A',
        ],
      ],
    ]);
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // Only 2 entity_test entities of bundle bar start with the letter B.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'bar',
      'filters' => [
        'first_letter' => [
          'first_letter' => 'B',
        ],
      ],
    ]);
    $this->assertCount(2, $plugin->getLinks()->toArray());
    $this->assertEquals(
      $test_entities_by_bundle_and_first_letter['bar']['B'],
      $this->extractEntityNames($plugin->getLinks()->toArray())
    );

    // Test multiple filter plugins together.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [
        'creation_time' => [
          'creation' => 'old',
        ],
        'first_letter' => [
          'first_letter' => 'A',
        ],
      ],
    ]);
    $this->assertCount(1, $plugin->getLinks()->toArray());
    $this->assertEquals([
      1 => $test_entities_by_bundle['foo'][1],
    ], $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Verify that the proper context has been passed down the plugin.
    // @todo This should be in a unit test.
    $state = $this->container->get('state');
    $this->assertEquals([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ], $state->get('internal_source_test_creation_time_context'));

    // There are no entities of bundle foo created more than two years ago and
    // name starting with the letter B.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [
        'creation_time' => [
          'creation' => 'old',
        ],
        'first_letter' => [
          'first_letter' => 'B',
        ],
      ],
    ]);
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // There are two entities of bundle bar created more than two years ago and
    // name starting with letter B.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'bar',
      'filters' => [
        'creation_time' => [
          'creation' => 'old',
        ],
        'first_letter' => [
          'first_letter' => 'B',
        ],
      ],
    ]);
    $this->assertEquals([
      8 => $test_entities_by_bundle['bar'][8],
      9 => $test_entities_by_bundle['bar'][9],
    ], $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Verify again the context.
    $this->assertEquals([
      'entity_type' => 'entity_test',
      'bundle' => 'bar',
    ], $state->get('internal_source_test_creation_time_context'));
  }

  /**
   * Tests that the proper cacheability metadata is returned by the plugin.
   */
  public function testCacheabilityMetadata(): void {
    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('internal');

    $links = $plugin->getLinks();
    $this->assertEquals([], $links->getCacheTags());
    $this->assertEquals([], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ]);
    $links = $plugin->getLinks();
    $this->assertEquals(['entity_test_list'], $links->getCacheTags());
    $this->assertEquals(['entity_test_view_grants'], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [
        'enabled' => [
          'enabled' => TRUE,
        ],
      ],
    ]);
    $links = $plugin->getLinks();
    $this->assertEquals([
      'enabled_plugin_test_tag',
      'entity_test_list',
    ], $links->getCacheTags());
    $this->assertEquals(['entity_test_view_grants'], $links->getCacheContexts());
    $this->assertEquals(1800, $links->getCacheMaxAge());

    // Create a test entity.
    $test_entity_one = EntityTest::create([
      'name' => $this->randomString(),
      'type' => 'foo',
    ]);
    $test_entity_one->save();

    $links = $plugin->getLinks();
    $this->assertEqualsCanonicalizing([
      'enabled_plugin_test_tag',
      'entity_test:' . $test_entity_one->id(),
      'entity_test_list',
    ], $links->getCacheTags());
    $this->assertEquals(['entity_test_view_grants'], $links->getCacheContexts());
    $this->assertEquals(1800, $links->getCacheMaxAge());

    // Add another entity.
    $test_entity_two = EntityTest::create([
      'name' => $this->randomString(),
      'type' => 'foo',
    ]);
    $test_entity_two->save();

    $links = $plugin->getLinks();
    $this->assertEqualsCanonicalizing([
      'enabled_plugin_test_tag',
      'entity_test:' . $test_entity_one->id(),
      'entity_test:' . $test_entity_two->id(),
      'entity_test_list',
    ], $links->getCacheTags());
    $this->assertEquals(['entity_test_view_grants'], $links->getCacheContexts());
    $this->assertEquals(1800, $links->getCacheMaxAge());
  }

  /**
   * Tests that the query alter using the event subscriber works.
   */
  public function testInternalSourceQueryAlter(): void {
    $test_entities = [];
    $test_entities_values = [
      [
        'name' => 'Entity one',
        'type' => 'foo',
      ],
      [
        'name' => 'Entity two',
        'type' => 'foo',
      ],
    ];

    foreach ($test_entities_values as $entity_values) {
      $entity = EntityTest::create($entity_values);
      $entity->save();
      $test_entities[$entity->id()] = $entity->label();
    }

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('internal');
    $plugin->setConfiguration([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ]);

    // The query has not yet been altered.
    $this->assertEquals($test_entities, $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Trigger the event subscriber to alter the query.
    $this->container->get('state')->set('internal_source_query_test_enable', TRUE);
    // The test query alter should filter out the first entity.
    unset($test_entities[1]);
    $this->assertEquals($test_entities, $this->extractEntityNames($plugin->getLinks()->toArray()));
    $metadata = $this->container->get('state')->get('internal_source_query_test_metadata');
    $this->assertEquals([
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
      'filters' => [],
    ], $metadata);
  }

  /**
   * Helper method to extract entity ID and name from an array of test entities.
   *
   * @param \Drupal\oe_link_lists\EntityAwareLinkInterface[] $links
   *   A list of link objects.
   *
   * @return array
   *   A list of entity labels, keyed by entity ID.
   */
  protected function extractEntityNames(array $links): array {
    $labels = [];

    foreach ($links as $link) {
      $entity = $link->getEntity();
      $labels[$entity->id()] = $entity->label();
    }

    return $labels;
  }

  /**
   * Provides an array of entity data to be used in the test.
   *
   * @return array
   *   An array of entity data.
   */
  protected function getTestEntities(): array {
    $two_years_ago = $this->container->get('datetime.time')->getRequestTime() - 2 * 12 * 365 * 24 * 60 * 60;
    return [
      [
        'name' => 'A' . $this->randomString(),
        'type' => 'foo',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'A' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'A' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'F' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'T' . $this->randomString(),
        'type' => 'bar',
      ],
      [
        'name' => 'S' . $this->randomString(),
        'type' => 'bar',
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'bar',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'bar',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'M' . $this->randomString(),
        'type' => 'bar',
      ],
    ];
  }

}
