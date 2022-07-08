<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_manual_source\FunctionalJavascript;

/**
 * Tests the translatability of manual link lists.
 */
class ManualLinkListTranslationTest extends ManualLinkListTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'locale',
    'language',
    'oe_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'manual', TRUE);
    $bundles = [
      'internal',
      'external',
    ];
    foreach ($bundles as $bundle) {
      \Drupal::service('content_translation.manager')->setEnabled('link_list_link', $bundle, TRUE);
    }

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('router.builder')->rebuild();

    $pages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'Page 2']);
    $page_two = reset($pages);
    $page_two->addTranslation('fr', ['title' => 'deuxieme page'])->save();

    $web_user = $this->drupalCreateUser([
      'bypass node access',
      'create manual link list',
      'edit manual link list',
      'view link list',
      'access link list canonical page',
      'create internal link list link',
      'create external link list link',
      'edit external link list link',
      'edit internal link list link',
      'translate any entity',
    ]);

    $this->drupalLogin($web_user);
  }

  /**
   * Tests that the link lists are translatable.
   */
  public function testManualLinkListTranslatability(): void {
    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Markup');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create an external link.
    $this->createInlineExternalLink('http://example.com', 'Test title', 'Test teaser');
    $this->createInlineInternalLink('1', 'Overridden title', 'Overridden teaser');
    $this->getSession()->getPage()->pressButton('Save');

    // Translate into FR.
    $link_list = $this->getLinkListByTitle('Test translation');
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);

    $this->getSession()->getPage()->fillField('Title', 'Test de traduction');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test la traduction admin titre');

    // Edit the external link.
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[1]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $links_wrapper->fillField('URL', 'http://traduction.com/fr');
    $links_wrapper->fillField('Title', 'Titre du test');
    $links_wrapper->fillField('Teaser', 'Description du test');
    $this->getSession()->getPage()->pressButton('Update Link');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Edit the internal link.
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[2]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $links_wrapper->fillField('Title', 'Titre redéfinie');
    $links_wrapper->fillField('Teaser', 'Teaser redéfinie');
    $this->getSession()->getPage()->pressButton('Update Link');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Save');

    // Assert we have the link list translated.
    $link_list = $this->getLinkListByTitle('Test translation', TRUE);
    $this->assertTrue($link_list->hasTranslation('fr'));
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('Test de traduction', $translation->get('title')->value);
    $this->assertEquals('Test la traduction admin titre', $translation->get('administrative_title')->value);

    // Navigate to the list and assert we still have the original values in EN.
    $this->drupalGet($link_list->toUrl());
    $this->assertEquals('Test title', $this->getSession()->getPage()->findAll('css', '.link-list-test--title')[0]->getText());
    $this->assertEquals('Test teaser', $this->getSession()->getPage()->findAll('css', '.link-list-test--teaser')[0]->getText());
    $this->assertEquals('http://example.com', $this->getSession()->getPage()->findAll('css', '.link-list-test--url')[0]->getText());
    $this->assertEquals('Overridden title', $this->getSession()->getPage()->findAll('css', '.link-list-test--title')[1]->getText());
    $this->assertEquals('Overridden teaser', $this->getSession()->getPage()->findAll('css', '.link-list-test--teaser')[1]->getText());
    $this->assertSession()->pageTextNotContains('Titre du test');
    $this->assertSession()->pageTextNotContains('Description du test');
    $this->assertSession()->pageTextNotContains('http://traduction.com/fr');
    $this->assertSession()->pageTextNotContains('Titre redéfinie');
    $this->assertSession()->pageTextNotContains('Teaser redéfinie');

    // Navigate to the list translation and assert we show translated values.
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));
    $this->assertEquals('Titre du test', $this->getSession()->getPage()->findAll('css', '.link-list-test--title')[0]->getText());
    $this->assertEquals('Description du test', $this->getSession()->getPage()->findAll('css', '.link-list-test--teaser')[0]->getText());
    $this->assertEquals('http://traduction.com/fr', $this->getSession()->getPage()->findAll('css', '.link-list-test--url')[0]->getText());
    $this->assertEquals('Titre redéfinie', $this->getSession()->getPage()->findAll('css', '.link-list-test--title')[1]->getText());
    $this->assertEquals('Teaser redéfinie', $this->getSession()->getPage()->findAll('css', '.link-list-test--teaser')[1]->getText());
    $this->assertSession()->pageTextNotContains('Test title');
    $this->assertSession()->pageTextNotContains('Test teaser');
    $this->assertSession()->pageTextNotContains('http://example.com');
    $this->assertSession()->pageTextNotContains('Overridden title');
    $this->assertSession()->pageTextNotContains('Overridden teaser');
  }

  /**
   * Tests that internal links render in the correct language.
   */
  public function testInternalLinkRendering(): void {
    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create some internal links that reference an untranslated node.
    $this->createInlineInternalLink('1', 'Overridden title', 'Overridden teaser');
    $this->createInlineInternalLink('1');

    // Create an internal link that reference a translated node.
    $this->createInlineInternalLink('2');
    $this->getSession()->getPage()->pressButton('Save');

    // Translate the first internal link programmatically.
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $links = \Drupal::entityTypeManager()->getStorage('link_list_link')->loadByProperties(['title' => 'Overridden title']);
    $link = reset($links);
    $link->addTranslation('fr', [
      'title' => 'Titre redéfinie',
      'teaser' => 'Teaser redéfinie',
    ])->save();

    // Assert the rendering in English.
    $this->assertSession()->linkExists('Overridden title');
    $this->assertSession()->linkExists('Page 1');
    $this->assertSession()->linkExists('Page 2');
    $this->assertSession()->linkNotExists('Titre redéfinie');

    // Assert the rendering in French.
    $link_list = $this->getLinkListByTitle('Test translation');
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));
    $this->assertSession()->linkNotExists('Overridden title');
    $this->assertSession()->linkExists('Titre redéfinie');
    $this->assertSession()->linkExists('Page 1');
    $this->assertSession()->linkNotExists('Page 2');
    $this->assertSession()->linkExists('deuxieme page');
  }

}
