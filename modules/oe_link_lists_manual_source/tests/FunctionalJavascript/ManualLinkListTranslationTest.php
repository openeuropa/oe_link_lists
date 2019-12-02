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
  protected function setUp() {
    parent::setUp();

    $pages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'Page 2']);
    $page_two = reset($pages);
    $page_two->addTranslation('fr', ['title' => 'deuxieme page'])->save();

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

    $web_user = $this->drupalCreateUser([
      'bypass node access',
      'administer link_lists',
      'administer link list link entities',
      'translate any entity',
    ]);
    $this->drupalLogin($web_user);
  }

  /**
   * Tests that the link lists are translatable.
   */
  public function testTranslateLinkList(): void {
    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create an external link.
    $this->createInlineExternalLink('http://example.com', 'Test title', 'Test teaser');
    $this->createInlineInternalLink('1', 'Overridden title', 'Overridden teaser');
    $this->getSession()->getPage()->pressButton('Save');

    // Translate into FR.
    $this->drupalGet('/link_list/1/translations/add/en/fr');
    $this->getSession()->getPage()->fillField('Title', 'Test de traduction');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test la traduction admin titre');

    // Change the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Baz');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Edit the external link.
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[1]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $links_wrapper->fillField('URL', 'http://example.com/fr');
    $links_wrapper->fillField('Title', 'Titre du test');
    $links_wrapper->fillField('Teaser', 'Teaser de test');
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
    $link_list = $this->getLinkListByTitle('Test translation');
    $this->assertTrue($link_list->hasTranslation('fr'));
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('Test de traduction', $translation->get('title')->value);
    $this->assertEquals('Test la traduction admin titre', $translation->get('administrative_title')->value);

    // Navigate to the list and assert we still have the original values in EN.
    $this->drupalGet('link_list/1');
    $this->assertSession()->linkExists('Test title');
    $this->assertSession()->linkExists('Overridden title');

    // Navigate to the list translation and assert we have translated values.
    $this->drupalGet('link_list/1', ['language' => \Drupal::languageManager()->getLanguage('fr')]);
    $this->assertSession()->pageTextContains('Titre du test');
    $this->assertSession()->pageTextContains('Teaser de test');
    $this->assertSession()->pageTextContains('http://example.com/fr');
    $this->assertSession()->pageTextContains('Titre redéfinie');
    $this->assertSession()->pageTextContains('Teaser redéfinie');
  }

  /**
   * Tests that internal links render in the correct language.
   */
  public function testInternalLinkRendering(): void {
    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test translation');
    $this->getSession()->getPage()->fillField('Administrative title', 'Test translation admin title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
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
    $link->addTranslation('fr', ['title' => 'Titre redéfinie', 'teaser' => 'Teaser redéfinie'])->save();

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