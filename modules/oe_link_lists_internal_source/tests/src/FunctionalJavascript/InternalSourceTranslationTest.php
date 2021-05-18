<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_internal_source\FunctionalJavascript;

/**
 * Tests that the internal source shows content in the current language.
 */
class InternalSourceTranslationTest extends InternalLinkSourceTestBase {

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

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 1',
      'body' => 'Page 1 body',
    ]);

    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 2',
      'body' => 'Page 2 body',
    ]);

    $page_two->addTranslation('fr', ['title' => 'deuxieme page'])->save();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('router.builder')->rebuild();
    $web_user = $this->drupalCreateUser([
      'bypass node access',
      'create dynamic link list',
      'edit dynamic link list',
      'view link list',
      'access link list canonical page',
      'translate any entity',
    ]);
    $this->drupalLogin($web_user);
  }

  /**
   * Link lists using the internal source plugin show translated content.
   */
  public function testInternalSourceTranslatedContent(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'Internal plugin test');
    $this->getSession()->getPage()->fillField('Title', 'Internal list');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->selectFieldOption('Link source', 'Internal');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Bundle', 'page');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // In English we see the EN versions.
    $this->assertSession()->pageTextContains('Page 1');
    $this->assertSession()->pageTextContains('Page 2');

    // In French we should see one of nodes in FR.
    $link_list = $this->getLinkListByTitle('Internal list');
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));
    $this->assertSession()->pageTextContains('Page 1');
    $this->assertSession()->pageTextContains('deuxieme page');
    $this->assertSession()->pageTextNotContains('Page 2');
  }

}
