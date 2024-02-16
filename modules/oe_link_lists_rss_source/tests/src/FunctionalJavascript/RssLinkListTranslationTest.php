<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_rss_source\FunctionalJavascript;

use Drupal\aggregator\FeedStorageInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the translatability of the link lists that use the RSS source.
 *
 * @group oe_link_lists
 * @deprecated
 */
class RssLinkListTranslationTest extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * The link storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $linkStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aggregator',
    'filter',
    'oe_link_lists',
    'oe_link_lists_rss_source',
    'oe_link_lists_test',
    'oe_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'dynamic', TRUE);
    \Drupal::service('router.builder')->rebuild();

    // Do not delete old aggregator items during these tests, since our sample
    // feeds have hardcoded dates in them (which may be expired when this test
    // is run).
    \Drupal::configFactory()->getEditable('aggregator.settings')->set('items.expire', FeedStorageInterface::CLEAR_NEVER)->save();

    // Mock the http client and factory to allow requests to certain RSS feeds.
    $http_client_mock = $this->getMockBuilder(Client::class)->getMock();
    $test_module_path = \Drupal::service('extension.list.module')->getPath('aggregator_test');
    $http_client_mock
      ->method('send')
      ->willReturnCallback(function (RequestInterface $request, array $options = []) use ($test_module_path) {
        switch ($request->getUri()) {
          case 'http://www.example.com/atom.xml':
            $filename = 'aggregator_test_atom.xml';
            break;

          case 'http://www.example.com/rss.xml':
            $filename = 'aggregator_test_rss091.xml';
            break;

          default:
            return new Response(404);
        }

        $filename = $test_module_path . DIRECTORY_SEPARATOR . $filename;
        return new Response(200, [], file_get_contents($filename));
      });

    $http_client_factory_mock = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $http_client_factory_mock->method('fromOptions')
      ->willReturn($http_client_mock);

    $this->container->set('http_client_factory', $http_client_factory_mock);

    $feed_storage = $this->container->get('entity_type.manager')->getStorage('aggregator_feed');
    $feed = $feed_storage->create([
      'title' => $this->randomString(),
      'url' => 'http://www.example.com/atom.xml',
    ]);
    $feed->save();
    $feed->refreshItems();

    $feed = $feed_storage->create([
      'title' => $this->randomString(),
      'url' => 'http://www.example.com/rss.xml',
    ]);
    $feed->save();
    $feed->refreshItems();

    $web_user = $this->drupalCreateUser([
      'bypass node access',
      'create dynamic link list',
      'edit dynamic link list',
      'view link list',
      'access link list canonical page',
      'translate any entity',
      'access news feeds',
    ]);

    $this->drupalLogin($web_user);
  }

  /**
   * Tests that a link link list can be translated to use different RSS sources.
   */
  public function testRssLinkListTranslatability(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Markup');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'RSS');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/atom.xml');

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    // Translate the link list.
    $link_list = $this->getLinkListByTitle('Test translation');
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);

    $this->getSession()->getPage()->fillField('Title', 'Test de traduction');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test la traduction admin titre');
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/rss.xml');
    $this->getSession()->getPage()->pressButton('Save');

    // Assert the list got translated.
    $link_list = $this->getLinkListByTitle('Test translation', TRUE);
    $this->assertTrue($link_list->hasTranslation('fr'));
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('Test de traduction', $translation->get('title')->value);
    $this->assertEquals('Test la traduction admin titre', $translation->get('administrative_title')->value);

    // Assert some items in EN.
    $this->drupalGet($link_list->toUrl());
    $this->assertSession()->pageTextContains('Atom-Powered Robots Run Amok');
    $this->assertSession()->pageTextContains('http://example.org/2003/12/13/atom03');
    $this->assertSession()->pageTextContains('Some text.');
    $this->assertSession()->pageTextNotContains('First example feed item title');
    $this->assertSession()->pageTextNotContains('http://example.com/example-turns-one');
    $this->assertSession()->pageTextNotContains('First example feed item description.');

    // Assert some items in FR where we use a completely different feed URL.
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));
    $this->assertSession()->pageTextContains('First example feed item title');
    $this->assertSession()->pageTextContains('http://example.com/example-turns-one');
    $this->assertSession()->pageTextContains('First example feed item description.');
    $this->assertSession()->pageTextNotContains('Atom-Powered Robots Run Amok');
    $this->assertSession()->pageTextNotContains('http://example.org/2003/12/13/atom03');
    $this->assertSession()->pageTextNotContains('Some text.');
  }

}
