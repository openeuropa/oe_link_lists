<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_rss_source\FunctionalJavascript;

/**
 * Tests the translatability of the link lists that use the RSS source.
 *
 * @group oe_link_lists
 */
class RssLinksTranslationTest extends RssLinksTestBase {

  /**
   * Tests that a link link list can be translated to use different RSS sources.
   */
  public function testRssLinksTranslatability(): void {
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

    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Markup');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'RSS links');
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
    $this->assertSession()->pageTextContainsOnce('Atom-Powered Robots Run Amok');
    $this->assertSession()->pageTextContainsOnce('http://example.org/2003/12/13/atom03');
    $this->assertSession()->pageTextContainsOnce('Some text.');
    $this->assertSession()->pageTextNotContains('First example feed item title');
    $this->assertSession()->pageTextNotContains('http://example.com/example-turns-one');
    $this->assertSession()->pageTextNotContains('First example feed item description.');

    // Assert some items in FR where we use a completely different feed URL.
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));
    $this->assertSession()->pageTextContainsOnce('First example feed item title');
    $this->assertSession()->pageTextContainsOnce('http://example.com/example-turns-one');
    $this->assertSession()->pageTextContainsOnce('First example feed item description.');
    $this->assertSession()->pageTextNotContains('Atom-Powered Robots Run Amok');
    $this->assertSession()->pageTextNotContains('http://example.org/2003/12/13/atom03');
    $this->assertSession()->pageTextNotContains('Some text.');
  }

}
