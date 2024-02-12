<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_rss_source\FunctionalJavascript;

use Drupal\aggregator\Entity\Feed;
use Drupal\Core\Url;

/**
 * Tests the feeds refresh capability.
 *
 * @group oe_link_lists
 */
class RssLinksFeedRefreshTest extends RssLinksTestBase {

  /**
   * Tests the feed refresh functionality.
   */
  public function testFeedRefresh(): void {
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

    // Create two feeds: one for RSS and one non-RSS.
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test RSS link list');
    $this->getSession()->getPage()->fillField('Title', 'Test RSS link list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Markup');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Link source', 'RSS links');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/atom.xml');
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    // Translate it.
    $link_list = $this->getLinkListByTitle('Test RSS link list');
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);
    $this->getSession()->getPage()->fillField('Title', 'Test de traduction');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test la traduction admin titre');
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/rss.xml');
    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test non-RSS link list');
    $this->getSession()->getPage()->fillField('Title', 'Test non-RSS link list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Markup');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // Assert we got two feeds.
    $feeds = Feed::loadMultiple();
    $this->assertCount(2, $feeds);
    // Keep track of the initial feed IDs.
    $feed_item_ids = $this->getFeedItemIds($feeds);
    $initial_expected_count = [
      'http://www.example.com/atom.xml' => 2,
      'http://www.example.com/rss.xml' => 8,
    ];
    foreach ($feed_item_ids as $url => $item_ids) {
      $this->assertCount($initial_expected_count[$url], $item_ids);
    }

    // Assert we cannot access the feed refresh form as anon.
    $link_lists['rss'] = $this->getLinkListByTitle('Test RSS link list');
    $link_lists['non_rss'] = $this->getLinkListByTitle('Test non-RSS link list');
    $this->drupalLogout();
    foreach ($link_lists as $link_list) {
      $this->assertFalse(Url::fromRoute('oe_link_lists_rss_source.feed_refresh', ['link_list' => $link_list->id()])->access());
    }

    // Log back in and assert that only the URL for the RSS-based link list is
    // accessible.
    $this->drupalLogin($web_user);
    $this->assertFalse(Url::fromRoute('oe_link_lists_rss_source.feed_refresh', ['link_list' => $link_lists['non_rss']->id()])->access());
    $this->assertTrue(Url::fromRoute('oe_link_lists_rss_source.feed_refresh', ['link_list' => $link_lists['rss']->id()])->access());

    // Go to the link list and refresh the feeds. Nothing should change.
    $this->drupalGet($link_lists['rss']->toUrl());
    $this->clickLink('Refresh feeds');
    $this->getSession()->getPage()->pressButton('Refresh');
    $this->assertSession()->pageTextContains('A number of 2 feeds have been refreshed.');
    // Same count of items.
    $feed_item_ids = $this->getFeedItemIds($feeds);
    foreach ($feed_item_ids as $url => $item_ids) {
      $this->assertCount($initial_expected_count[$url], $item_ids);
    }

    // Now mock the addition of new items to the feed sources.
    \Drupal::state()->set('oe_link_lists_rss_source_test_extra_atom', TRUE);

    $this->clickLink('Refresh feeds');
    $this->getSession()->getPage()->pressButton('Refresh');
    $this->assertSession()->pageTextContains('A number of 2 feeds have been refreshed.');

    // We now have an extra item for each.
    $feed_item_ids = $this->getFeedItemIds($feeds);
    foreach ($feed_item_ids as $url => $item_ids) {
      $this->assertCount($initial_expected_count[$url] + 1, $item_ids, sprintf('The feed with the url %s has an extra item', $url));
    }

    // Assert that on the non-RSS link list we don't see the local task.
    $this->drupalGet($link_lists['non_rss']->toUrl());
    $this->assertSession()->linkNotExists('Refresh feeds');

    // Assert that on the link list translation we cannot see the refresh
    // link.
    $this->drupalGet('/fr/link_list/' . $link_lists['rss']->id(), ['external' => FALSE]);
    $this->assertSession()->pageTextContains('An extra item title');
    $this->assertSession()->linkNotExists('Refresh feeds');
  }

  /**
   * Loads the array of feed item IDs grouped by feed URL.
   *
   * @param \Drupal\aggregator\FeedInterface[] $feeds
   *   The feeds.
   */
  protected function getFeedItemIds(array $feeds): array {
    $ids = [];
    foreach ($feeds as $feed) {
      $ids[$feed->getUrl()] = \Drupal::entityTypeManager()->getStorage('aggregator_item')->getQuery()
        ->condition('fid', $feed->id())
        ->accessCheck(FALSE)
        ->execute();
    }

    return $ids;
  }

}
