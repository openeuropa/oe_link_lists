<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_internal_source\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_link_lists_internal_source_test\Plugin\InternalLinkSourceFilter\CreationTime;
use Drupal\oe_link_lists_internal_source_test\Plugin\InternalLinkSourceFilter\Enabled;
use Drupal\oe_link_lists_internal_source_test\Plugin\InternalLinkSourceFilter\FirstLetter;

/**
 * Tests the internal link source filter plugin manager.
 *
 * @group oe_link_lists
 * @covers \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginManager
 */
class InternalLinkSourceFilterPluginManagerTest extends KernelTestBase {

  /**
   * The internal link source filter plugin manager.
   *
   * @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginManagerInterface
   */
  protected $manager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'oe_link_lists_internal_source',
    'oe_link_lists_internal_source_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manager = $this->container->get('plugin.manager.oe_link_lists.internal_source_filter');
    $this->state = $this->container->get('state');
  }

  /**
   * Tests the getApplicablePlugins() method.
   */
  public function testGetApplicablePlugins(): void {
    // Test the case of plugins that support only one specific bundle of a
    // certain entity type.
    // The Enabled plugin specifies support for the page node bundle.
    $this->assertPlugins([
      'enabled' => Enabled::class,
    ], $this->manager->getApplicablePlugins('node', 'page'));
    // No plugins support the news node bundle.
    $this->assertEquals([], $this->manager->getApplicablePlugins('node', 'news'));

    // Test the case of plugins that support all the bundles of an entity type.
    // The First letter plugin supports all the entity_test bundles.
    $this->assertPlugins([
      'first_letter' => FirstLetter::class,
    ], $this->manager->getApplicablePlugins('entity_test', 'baz'));

    // The Enabled plugin supports two different bundles of entity_test.
    $this->assertPlugins([
      'enabled' => Enabled::class,
      'first_letter' => FirstLetter::class,
    ], $this->manager->getApplicablePlugins('entity_test', 'foo'));
    $this->assertPlugins([
      'enabled' => Enabled::class,
      'first_letter' => FirstLetter::class,
    ], $this->manager->getApplicablePlugins('entity_test', 'bar'));

    // Make the CreationTime plugin isApplicable() plugin method return true for
    // the bar entity_test bundle.
    $this->state->set('internal_source_test_creation_time_applicable_entity_types', ['entity_test' => ['bar']]);
    // All the plugins support the bar bundle now.
    $this->assertPlugins([
      'creation_time' => CreationTime::class,
      'enabled' => Enabled::class,
      'first_letter' => FirstLetter::class,
    ], $this->manager->getApplicablePlugins('entity_test', 'bar'));

    // Test the case of plugins that don't have an entity type specified.
    // No plugins are present for image media bundle.
    $this->assertEquals([], $this->manager->getApplicablePlugins('media', 'image'));
    // Make the CreationTime plugin isApplicable() plugin method return true for
    // the image media bundle.
    $this->state->set('internal_source_test_creation_time_applicable_entity_types', ['media' => ['image']]);
    // Verify that the Bar plugin is returned correctly.
    $this->assertPlugins([
      'creation_time' => CreationTime::class,
    ], $this->manager->getApplicablePlugins('media', 'image'));
    // Verify that the isApplicable() plugin method is invoked with the correct
    // entity type and bundle parameters.
    // The CreationTime plugin is not applicable to the video media bundle.
    $this->assertEquals([], $this->manager->getApplicablePlugins('media', 'video'));

    // Verify that the isApplicable() plugin method is always invoked to
    // determine the applicability of a plugin, given any combination of
    // supported entity types in the plugin annotation.
    // Make the CreationTime plugin support the comment entity type, any
    // bundles of it.
    $this->state->set('internal_source_test_creation_time_definition', [
      'comment' => [],
    ]);
    // Flush the definitions so the info alter hook gets run.
    $this->manager->clearCachedDefinitions();
    // The isApplicable() method of Bar is still only returning true for image
    // medias, so the plugin won't be applicable to comment bundles.
    $this->assertEquals([], $this->manager->getApplicablePlugins('comment', 'foo'));
    // Make the Bar plugin apply to the foo comment bundle.
    $this->state->set('internal_source_test_creation_time_applicable_entity_types', ['comment' => ['foo']]);
    $this->assertPlugins([
      'creation_time' => CreationTime::class,
    ], $this->manager->getApplicablePlugins('comment', 'foo'));

    // Make the CreationTime plugin support only baz comment bundles.
    $this->state->set('internal_source_test_creation_time_definition', [
      'comment' => ['baz'],
    ]);
    $this->manager->clearCachedDefinitions();
    // No plugins will be returned as the isApplicable() method is correctly
    // invoked and has a negative result.
    $this->assertEquals([], $this->manager->getApplicablePlugins('comment', 'baz'));
    // Make the Bar plugin apply to the baz comment bundle.
    $this->state->set('internal_source_test_creation_time_applicable_entity_types', ['comment' => ['baz']]);
    $this->assertPlugins([
      'creation_time' => CreationTime::class,
    ], $this->manager->getApplicablePlugins('comment', 'baz'));
  }

  /**
   * Asserts size, plugin id and class of a list of plugins.
   *
   * @param array $expected
   *   A list of expected plugin classes, keyed by plugin ID.
   * @param array $plugins
   *   The list of plugins to verify.
   */
  protected function assertPlugins(array $expected, array $plugins): void {
    $this->assertSameSize($expected, $plugins);
    $this->assertEquals(array_keys($expected), array_keys($plugins));
    foreach ($expected as $plugin_id => $class) {
      $this->assertInstanceOf($class, $plugins[$plugin_id]);
    }
  }

}
