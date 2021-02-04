<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_rss_source\Kernel;

use Drupal\aggregator\FeedStorageInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_link_lists\DefaultEntityLink;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the RSS link source plugin.
 *
 * @group oe_link_lists
 * @coversDefaultClass \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource
 */
class RssLinkSourcePluginTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'aggregator',
    'options',
    'system',
    'language',
    'content_translation',
    'oe_link_lists',
    'oe_link_lists_test',
    'oe_link_lists_rss_source',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_link_lists_rss_source_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');

    /** @var \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('rss', $form_state->get('plugin_configuration') ?? []);

    $form['#tree'] = TRUE;
    $form['plugin'] = [];
    $sub_form_state = SubformState::createForSubform($form['plugin'], $form, $form_state);
    $form['plugin'] = $plugin->buildConfigurationForm($form['plugin'], $sub_form_state);

    // Save the plugin for later use.
    $form_state->set('plugin', $plugin);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource $plugin */
    $plugin = $form_state->get('plugin');
    $sub_form_state = SubformState::createForSubform($form['plugin'], $form, $form_state);
    $plugin->validateConfigurationForm($form, $sub_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource $plugin */
    $plugin = $form_state->get('plugin');
    $sub_form_state = SubformState::createForSubform($form['plugin'], $form, $form_state);
    $plugin->submitConfigurationForm($form, $sub_form_state);
  }

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
      'language',
      'content_translation',
    ]);
    $this->installConfig('aggregator');
    $this->installEntitySchema('aggregator_feed');
    $this->installEntitySchema('aggregator_item');

    // Do not delete old aggregator items during these tests, since our sample
    // feeds have hardcoded dates in them (which may be expired when this test
    // is run).
    $this->config('aggregator.settings')->set('items.expire', FeedStorageInterface::CLEAR_NEVER)->save();

    // Mock the http client and factory to allow requests to certain RSS feeds.
    $http_client_mock = $this->getMockBuilder(Client::class)->getMock();
    $test_module_path = drupal_get_path('module', 'aggregator_test');
    $http_client_mock
      ->method('send')
      ->willReturnCallback(function (RequestInterface $request, array $options = []) use ($test_module_path) {
        switch ($request->getUri()) {
          case 'http://www.example.com/atom.xml':
            $filename = $test_module_path . DIRECTORY_SEPARATOR . 'aggregator_test_atom.xml';
            break;

          case 'http://www.example.com/rss.xml':
            $filename = $test_module_path . DIRECTORY_SEPARATOR . 'aggregator_test_rss091.xml';
            break;

          case 'http://ec.europa.eu/rss.xml':
            $filename = drupal_get_path('module', 'oe_link_lists_rss_source') . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'rss_links_source_test_rss.xml';
            break;

          default:
            return new Response(404);
        }

        return new Response(200, [], file_get_contents($filename));
      });

    $http_client_factory_mock = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $http_client_factory_mock->method('fromOptions')
      ->willReturn($http_client_mock);

    $this->container->set('http_client_factory', $http_client_factory_mock);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'dynamic', TRUE);
  }

  /**
   * Tests the plugin form.
   */
  public function testPluginForm(): void {
    // Provide an existing plugin configuration.
    $form_state = new FormState();
    $form_state->set('plugin_configuration', ['url' => 'http://www.example.com/test.xml']);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->buildForm($this, $form_state);
    $this->render($form);

    // Verify that the plugin subform is under a main "plugin" tree and that is
    // using the existing configuration value.
    $this->assertFieldByName('plugin[url]', 'http://www.example.com/test.xml');

    // The default value for new plugins is empty.
    $form = $form_builder->buildForm($this, $form_state);
    $this->render($form);
    $this->assertFieldByName('plugin[url]', '');

    $form_state = new FormState();
    $form_builder->submitForm($this, $form_state);
    // Assert that the URL form field is required.
    $this->assertEquals(['plugin][url' => 'The resource URL field is required.'], $form_state->getErrors());

    $form_state = new FormState();
    $form_state->setValue(['plugin', 'url'], 'invalid url');
    $form_builder->submitForm($this, $form_state);
    // Assert that the URL form element expects valid URLs.
    $this->assertEquals(['plugin][url' => 'The URL <em class="placeholder">invalid url</em> is not valid.'], $form_state->getErrors());

    // The form submits correctly when a valid URL is provided.
    $form_state = new FormState();
    $form_state->setValue(['plugin', 'url'], 'http://www.example.com/atom.xml');
    $form_builder->submitForm($this, $form_state);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Tests the plugin configuration.
   *
   * Tests that the RSS plugin configuration generates the necessary
   * aggregator feeds and items.
   *
   * @covers ::preSave
   */
  public function testPluginConfiguration(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $feed_storage = $entity_type_manager->getStorage('aggregator_feed');
    $item_storage = $entity_type_manager->getStorage('aggregator_item');

    // Confirm there are no aggregates before creating the plugin.
    $this->assertCount(0, $feed_storage->loadMultiple());
    $this->assertCount(0, $item_storage->loadMultiple());

    // Create a link list.
    $link_list_storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $values = [
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ];

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->create($values);

    // Configure the link list.
    $configuration = [
      'source' => [
        'plugin' => 'rss',
        'plugin_configuration' => [
          'url' => 'http://www.example.com/atom.xml',
        ],
      ],
      'display' => [
        'plugin' => 'bar',
        'plugin_configuration' => ['link' => FALSE],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $this->assertEquals($configuration, $link_list->getConfiguration());

    $link_list->save();

    // Assert the aggregator feeds and items.
    $feeds = $feed_storage->loadMultiple();
    $this->assertCount(1, $feeds);
    $this->assertEqual($feeds[1]->getUrl(), 'http://www.example.com/atom.xml');
    $this->assertCount(2, $item_storage->loadMultiple());

    // Test with adding translation.
    $link_list->addTranslation('fr', $link_list->toArray());

    $configuration['source']['plugin_configuration']['url'] = 'http://ec.europa.eu/rss.xml';
    $translation = $link_list->getTranslation('fr');
    $translation->setConfiguration($configuration);

    $expected_source = [
      'plugin' => 'rss',
      'plugin_configuration' => [
        'url' => 'http://ec.europa.eu/rss.xml',
      ],
    ];
    $this->assertEquals($expected_source, $translation->getConfiguration()['source']);

    $translation->save();

    // New aggregator items were added.
    $feeds = $feed_storage->loadMultiple();
    $this->assertCount(2, $feeds);
    $this->assertEqual($feeds[1]->getUrl(), 'http://www.example.com/atom.xml');
    $this->assertEqual($feeds[2]->getUrl(), 'http://ec.europa.eu/rss.xml');
    $this->assertCount(4, $item_storage->loadMultiple());

    // Add translation with an existing rss source.
    $link_list->addTranslation('de', $link_list->toArray());

    $translation = $link_list->getTranslation('de');
    $translation->setConfiguration($configuration);

    $expected_source = [
      'plugin' => 'rss',
      'plugin_configuration' => [
        'url' => 'http://ec.europa.eu/rss.xml',
      ],
    ];
    $this->assertEquals($expected_source, $translation->getConfiguration()['source']);

    $translation->save();

    // No aggregator items were added.
    $this->assertCount(2, $feed_storage->loadMultiple());
    $this->assertCount(4, $item_storage->loadMultiple());
  }

  /**
   * Tests that the plugin returns the links.
   *
   * @covers ::getLinks()
   * @covers ::getFeed
   */
  public function testLinks(): void {
    // Create two test feeds.
    $feed_storage = $this->container->get('entity_type.manager')->getStorage('aggregator_feed');
    $feeds = [
      'atom' => 'http://www.example.com/atom.xml',
      'rss' => 'http://www.example.com/rss.xml',
    ];
    foreach ($feeds as $name => $url) {
      $feed = $feed_storage->create([
        'title' => $this->randomString(),
        'url' => $url,
      ]);
      $feed->save();
      $feed->refreshItems();
      // Save the feed ID to check the cache tags later.
      $feeds[$name] = $feed->id();
    }

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');

    /** @var \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('rss');
    // Test a plugin with empty configuration.
    $this->assertTrue($plugin->getLinks()->isEmpty());

    // Tests that the plugin doesn't break if it's referring a non-existing
    // feed, for example one that existed in the system and has been removed.
    $plugin->setConfiguration(['url' => 'http://www.example.com/deleted.xml']);
    $this->assertTrue($plugin->getLinks()->isEmpty());

    // Generate the expected links.
    $expected = $this->getExpectedLinks();

    $plugin->setConfiguration(['url' => 'http://www.example.com/atom.xml']);
    $links = $plugin->getLinks();
    $this->assertEquals($expected['atom'], $links->toArray());
    $this->assertEquals(['aggregator_feed:' . $feeds['atom']], $links->getCacheTags());
    $this->assertEquals([], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    $plugin->setConfiguration(['url' => 'http://www.example.com/rss.xml']);
    $links = $plugin->getLinks();
    $this->assertEquals($expected['rss'], $links->toArray());
    $this->assertEquals(['aggregator_feed:' . $feeds['rss']], $links->getCacheTags());
    $this->assertEquals([], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    // Check the limit and offset parameters.
    $links = $plugin->getLinks(5)->toArray();
    $this->assertEquals(array_slice($expected['rss'], 0, 5), $links);

    $links = $plugin->getLinks(5, 2)->toArray();
    $this->assertEquals(array_slice($expected['rss'], 2, 5), $links);
  }

  /**
   * Tests that the RSS teaser is stripped according to the aggregator config.
   */
  public function testLinkTeaserTags(): void {
    $feed_storage = $this->container->get('entity_type.manager')->getStorage('aggregator_feed');
    $url = 'http://ec.europa.eu/rss.xml';

    /** @var \Drupal\aggregator\FeedInterface $feed */
    $feed = $feed_storage->create([
      'title' => $this->randomString(),
      'url' => $url,
    ]);
    $feed->save();
    $feed->refreshItems();

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');

    /** @var \Drupal\oe_link_lists_rss_source\Plugin\LinkSource\RssLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('rss');
    $plugin->setConfiguration(['url' => $url]);

    $this->assertEquals([
      'Second example feed item title',
      'First example feed item title',
    ], $this->getLinkTitles($plugin->getLinks()->toArray()));

    $this->assertEquals([
      '<p>Second example feed item <a href="http://example.com">description</a> with link.</p>',
      '<p>First example feed item.</p>',
    ], $this->renderLinksTeaser($plugin->getLinks()->toArray()));

    // Configure the aggregator to strip link tags but still allow paragraphs.
    $this->container->get('config.factory')
      ->getEditable('aggregator.settings')
      ->set('items.allowed_html', '<p>')
      ->save();

    $this->assertEquals([
      '<p>Second example feed item description with link.</p>',
      '<p>First example feed item.</p>',
    ], $this->renderLinksTeaser($plugin->getLinks()->toArray()));

    // Strip all tags now.
    $this->container->get('config.factory')
      ->getEditable('aggregator.settings')
      ->set('items.allowed_html', '')
      ->save();

    $this->assertEquals([
      'Second example feed item description with link.',
      'First example feed item.',
    ], $this->renderLinksTeaser($plugin->getLinks()->toArray()));
  }

  /**
   * Returns expected feed data.
   *
   * @return array
   *   List of LinkInterface objects.
   */
  protected function getExpectedLinks(): array {
    $feed_storage = $this->container->get('entity_type.manager')->getStorage('aggregator_feed');
    $item_storage = $this->container->get('entity_type.manager')->getStorage('aggregator_item');
    // Mimic the RssLinkSource::getAllowedTeaserTags() method.
    $allowed_tags = preg_split('/\s+|<|>/', $this->config('aggregator.settings')->get('items.allowed_html'), -1, PREG_SPLIT_NO_EMPTY);

    $links = [];
    $rss_urls = [
      'atom' => 'http://www.example.com/atom.xml',
      'rss' => 'http://www.example.com/rss.xml',
    ];
    foreach ($rss_urls as $name => $rss_url) {
      $feeds = $feed_storage->loadByProperties(['url' => $rss_url]);
      $feed = reset($feeds);
      foreach ($item_storage->loadByFeed($feed->id()) as $item) {
        /** @var \Drupal\aggregator\ItemInterface $item */
        $url = $item->getLink() ? Url::fromUri($item->getLink()) : Url::fromRoute('<front>');
        $link = new DefaultEntityLink($url, $item->getTitle(), [
          '#markup' => $item->getDescription(),
          '#allowed_tags' => $allowed_tags,
        ]);
        $link->setEntity($item);
        $links[$name][] = $link;
      }
    }

    return $links;
  }

  /**
   * Renders the teaser for a list of link elements.
   *
   * @param array $links
   *   A list of LinkInterface objects.
   *
   * @return array
   *   The rendered teasers.
   */
  protected function renderLinksTeaser(array $links): array {
    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = $this->container->get('renderer');

    $teasers = [];
    foreach ($links as $link) {
      $teaser = $link->getTeaser();
      $teasers[] = (string) $renderer->renderRoot($teaser);
    }

    return $teasers;
  }

  /**
   * Retrieve the link titles for assertion.
   *
   * @param array $links
   *   A list of LinkInterface objects.
   *
   * @return array
   *   The link titles.
   */
  protected function getLinkTitles(array $links): array {
    $titles = [];
    foreach ($links as $link) {
      $titles[] = $link->getTitle();
    }

    return $titles;
  }

}
