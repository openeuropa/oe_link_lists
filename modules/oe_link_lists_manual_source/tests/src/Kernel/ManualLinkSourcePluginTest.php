<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_manual_source\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Tests the manual link source plugin.
 *
 * @covers \Drupal\oe_link_lists_manual_source\Plugin\LinkSource\ManualLinkSource
 */
class ManualLinkSourcePluginTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'oe_link_lists_manual_source',
    'oe_link_lists_manual_source_test',
    'entity_reference_revisions',
    'composite_reference',
    'inline_entity_form',
    'field',
    'filter',
    'link',
    'node',
    'system',
    'text',
    'user',
    'content_translation',
    'locale',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('link_list_link');
    $this->installEntitySchema('link_list');
    $this->installEntitySchema('configurable_language');
    $this->installSchema('node', 'node_access');
    $this->installConfig([
      'field',
      'filter',
      'node',
      'system',
      'oe_link_lists_manual_source',
      'oe_link_lists_manual_source_test',
      'entity_reference_revisions',
      'composite_reference',
      'language',
      'content_translation',
    ]);

    $this->createContentType(['type' => 'page']);
  }

  /**
   * Tests the getLinks() method.
   *
   * @covers ::getLinks
   */
  public function testGetLinks(): void {
    // Create a node to be used by an internal link.
    $node_one = $this->createNode(['type' => 'page']);

    // Create an internal content, external and internal route link.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $link_storage = $entity_type_manager->getStorage('link_list_link');
    $internal_link_one = $link_storage->create([
      'bundle' => 'internal',
      'target' => $node_one->id(),
      'status' => 1,
    ]);
    $internal_link_one->save();

    $external_link = $link_storage->create([
      'bundle' => 'external',
      'url' => 'http://example.com',
      'title' => 'Example title',
      'teaser' => 'Example teaser',
      'status' => 1,
    ]);
    $external_link->save();

    $internal_route = $link_storage->create([
      'bundle' => 'internal_route',
      'url' => '/user',
      'title' => 'User page',
      'teaser' => 'User page teaser',
      'status' => 1,
    ]);
    $internal_route->save();

    // Create a list that references one internal and one external link.
    $list_storage = $entity_type_manager->getStorage('link_list');
    $list = $list_storage->create([
      'bundle' => 'manual',
      'links' => [
        $external_link,
        $internal_link_one,
        $internal_route,
      ],
      'status' => 1,
    ]);
    $list->save();

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_manual_source\Plugin\LinkSource\ManualLinkSource $plugin */
    $plugin_configuration = $list->getConfiguration()['source']['plugin_configuration'];
    $plugin = $plugin_manager->createInstance('manual_links', $plugin_configuration);

    $links = $plugin->getLinks();
    // Only the internal content and external links get resolved.
    $this->assertCount(2, $links);

    // Enable the resolver.
    $this->container->get('state')->set('oe_link_lists_manual_source_test_subscriber_resolve', TRUE);

    // Now there should be 3 links.
    $links = $plugin->getLinks();
    $this->assertCount(3, $links);

    $this->assertEquals('Example title', $links[0]->getTitle());
    $this->assertEquals($node_one->label(), $links[1]->getTitle());
    $this->assertEquals('User page', $links[2]->getTitle());

    // Assert we can filter the amount of links we get.
    $links = $plugin->getLinks(1);
    $this->assertCount(1, $links);
    $this->assertEquals('Example title', $links[0]->getTitle());

    // Verify the offset.
    $links = $plugin->getLinks(NULL, 1);
    $this->assertCount(2, $links);
    $this->assertEquals($node_one->label(), $links[0]->getTitle());
    $this->assertEquals('User page', $links[1]->getTitle());
    $this->assertEquals('User page teaser', $links[1]->getTeaser()['#markup']);
  }

  /**
   * Tests that the proper cacheability metadata is returned by the plugin.
   */
  public function testCacheabilityMetadata(): void {
    // Create some nodes to be used by internal links.
    $node_one = $this->createNode(['type' => 'page']);
    $node_two = $this->createNode(['type' => 'page']);

    // Create some internal and external links.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $link_storage = $entity_type_manager->getStorage('link_list_link');
    $internal_link_one = $link_storage->create([
      'bundle' => 'internal',
      'target' => $node_one->id(),
      'status' => 1,
    ]);
    $internal_link_one->save();
    $internal_link_two = $link_storage->create([
      'bundle' => 'internal',
      'target' => $node_two->id(),
      'status' => 1,
    ]);
    $internal_link_two->save();

    $external_link = $link_storage->create([
      'bundle' => 'external',
      'url' => 'http://example.com',
      'title' => 'Example title',
      'teaser' => 'Example teaser',
      'status' => 1,
    ]);
    $external_link->save();

    // Create a list that references one internal and one external link.
    $list_storage = $entity_type_manager->getStorage('link_list');
    $list = $list_storage->create([
      'bundle' => 'manual',
      'links' => [
        $external_link,
        $internal_link_one,
      ],
      'status' => 1,
    ]);
    $list->save();

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_manual_source\Plugin\LinkSource\ManualLinkSource $plugin */
    $plugin_configuration = $list->getConfiguration()['source']['plugin_configuration'];
    $plugin = $plugin_manager->createInstance('manual_links', $plugin_configuration);

    $links = $plugin->getLinks();
    $this->assertEqualsCanonicalizing([
      'link_list_link:1',
      'link_list_link:3',
      'node:1',
    ], $links->getCacheTags());
    $this->assertEquals([], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    // Add another internal link to the list.
    $list->set('links', [
      $internal_link_two,
      $external_link,
      $internal_link_one,
    ]);
    $list->save();

    $plugin_configuration = $list->getConfiguration()['source']['plugin_configuration'];
    $plugin = $plugin_manager->createInstance('manual_links', $plugin_configuration);

    $links = $plugin->getLinks();
    $this->assertEqualsCanonicalizing([
      'link_list_link:1',
      'link_list_link:2',
      'link_list_link:3',
      'node:1',
      'node:2',
    ], $links->getCacheTags());
    $this->assertEquals([], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());
  }

  /**
   * Tests manual translations of link lists.
   */
  public function testManualLinkTranslations(): void {
    // Create FR language.
    $language = ConfigurableLanguage::createFromLangcode('fr');
    $language->save();

    // Configure the language negotiation.
    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', [
      'en' => 'en',
      'fr' => 'fr',
    ])->save();

    $this->container->get('content_translation.manager')->setEnabled('link_list', 'manual', TRUE);
    $this->container->get('content_translation.manager')->setEnabled('link_list_link', 'external', TRUE);
    $this->container->get('kernel')->rebuildContainer();
    $this->container->get('router.builder')->rebuild();

    $language_manager = $this->container->get('language_manager');

    // Create an external link.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $link_storage = $entity_type_manager->getStorage('link_list_link');

    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $external_link */
    $external_link = $link_storage->create([
      'bundle' => 'external',
      'url' => 'http://example.com',
      'title' => 'Example title',
      'teaser' => 'Example teaser',
      'status' => 1,
      'langcode' => 'en',
    ]);
    $translation = $external_link->addTranslation('fr', $external_link->toArray());
    $translation->set('url', 'http://example.com/fr');
    $translation->set('title', 'Example title FR');
    $external_link->save();

    // Create a list that references the link..
    $list_storage = $entity_type_manager->getStorage('link_list');
    $list = $list_storage->create([
      'title' => 'My list',
      'bundle' => 'manual',
      'links' => [
        $external_link,
      ],
      'status' => 1,
      'langcode' => 'en',
    ]);
    $translation = $list->addTranslation('fr', $list->toArray());
    $translation->set('title', 'My list FR');
    $list->save();

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_manual_source\Plugin\LinkSource\ManualLinkSource $plugin */
    $plugin_configuration = $list->getConfiguration()['source']['plugin_configuration'];
    $plugin = $plugin_manager->createInstance('manual_links', $plugin_configuration);

    $links = $plugin->getLinks();
    $this->assertCount(1, $links);

    /** @var \Drupal\oe_link_lists\LinkInterface $link */
    $link = $links->offsetGet(0);
    $this->assertEquals('Example title', $link->getTitle());
    $this->assertEquals('http://example.com', $link->getUrl()->toString());

    // Set the current "site" language to FR and assert we get the correct
    // links.
    $this->container->get('language.default')->set($language);
    $language_manager->reset();

    $links = $plugin->getLinks();
    $this->assertCount(1, $links);

    /** @var \Drupal\oe_link_lists\LinkInterface $link */
    $link = $links->offsetGet(0);
    $this->assertEquals('Example title FR', $link->getTitle());
    $this->assertEquals('http://example.com/fr', $link->getUrl()->toString());

    // Set back the "site" language.
    $this->container->get('language.default')->set($language_manager->getLanguage('en'));
    $language_manager->reset();

    // Assert that our configured link list link IDs are the same across both
    // translations (copied from the links reference field to the serialized
    // config).
    $link_list = $this->getLinkListByTitle('My list', TRUE);
    $configured_ids = [];
    foreach (['en', 'fr'] as $langcode) {
      $translation = $link_list->getTranslation($langcode);
      $values = $translation->get('configuration')->source['plugin_configuration']['links'];
      $configured_ids[$langcode] = reset($values);
    }

    $this->assertEquals($configured_ids['en'], $configured_ids['fr']);

    // Duplicate the link list and its children (like entity clone does) and
    // assert the same for the duplicate.
    $new = $link_list->createDuplicate();
    $new_links = [];
    foreach ($link_list->get('links') as $value) {
      $link = $value->get('entity')->getTarget()->getValue();
      $new_link = $link->createDuplicate();
      $new_link->save();
      $new_links[] = $new_link;
    }
    $new->set('links', $new_links);
    $new->save();

    $this->assertNotNull($new->id());
    $configured_ids = [];
    foreach (['en', 'fr'] as $language) {
      $translation = $new->getTranslation($language);
      $configuration = $translation->getConfiguration();
      $values = $configuration['source']['plugin_configuration']['links'];
      $configured_ids[$language] = reset($values);
    }

    $this->assertEquals($configured_ids['en'], $configured_ids['fr']);
  }

}
