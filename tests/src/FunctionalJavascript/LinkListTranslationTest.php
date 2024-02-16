<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Tests the link list translation form.
 *
 * @group oe_link_lists
 */
class LinkListTranslationTest extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'link',
    'oe_link_lists',
    'oe_link_lists_test',
    'content_translation',
    'locale',
    'language',
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

    $web_user = $this->drupalCreateUser([
      'bypass node access',
      'administer link_lists',
      'translate any entity',
    ]);

    $this->drupalLogin($web_user);
  }

  /**
   * Tests that a link list configuration can be translated selectively.
   */
  public function testLinkListConfigurationTranslationForm(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'Admin title test');
    $this->getSession()->getPage()->fillField('Title', 'Title test');

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Configurable non translatable source');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Titles with optional link');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Configure the "More link" plugin..
    $this->getSession()->getPage()->selectFieldOption('Number of items', '2');
    $this->getSession()->getPage()->selectFieldOption('More link', 'Configurable non translatable more link');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Non translatable text message');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // Try to translate the list.
    $link_list = $this->getLinkListByTitle('Title test');
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);

    // Assert that all form elements that are not translatable are disabled. In
    // this case, all elements should be disabled as there is nothing
    // translatable.
    $this->assertSession()->fieldDisabled('Link source');
    $this->assertSession()->fieldDisabled('The source non translatable string');
    $this->assertSession()->fieldDisabled('Link display');
    $this->assertSession()->fieldDisabled('Link');
    $this->assertSession()->fieldDisabled('Number of items');
    $this->assertSession()->fieldDisabled('More link');
    $this->assertSession()->fieldDisabled('The more link configuration');
    $this->assertSession()->fieldDisabled('No results behaviour');
    $this->assertSession()->fieldDisabled('The non-translatable message you want shown');

    $this->drupalGet($link_list->toUrl('edit-form'));

    // Select and configure a source plugin with translatable elements.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Complex form');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The source translatable string', 'I can be translated');
    $this->getSession()->getPage()->fillField('The source non translatable string', 'I cannot be translated');

    // Select and configure the display plugin with translatable elements.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Translatable form');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The display translatable string', 'I can be translated');
    $this->getSession()->getPage()->fillField('The display non translatable string', 'I cannot be translated');

    // Select and configure the no results behaviour plugin with translatable
    // elements.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Text message');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The message you want shown', 'The no results text');

    // Configure the "More link" which also has translatable elements.
    $this->getSession()->getPage()->selectFieldOption('Number of items', '2');
    $this->getSession()->getPage()->selectFieldOption('More link', 'Custom link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Target', 'http://example.com/more-link');
    $this->getSession()->getPage()->checkField('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->getSession()->getPage()->fillField('More link label', 'Custom more link');
    $this->getSession()->getPage()->pressButton('Save');

    // Try to translate the list.
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);

    // Assert that all form elements that are not translatable are disabled.
    $this->assertSession()->fieldDisabled('Link source');
    $this->assertSession()->fieldDisabled('The source non translatable string');
    $this->assertSession()->fieldDisabled('The display non translatable string');
    $this->assertSession()->fieldDisabled('Link display');
    $this->assertSession()->fieldDisabled('Number of items');
    $this->assertSession()->fieldDisabled('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->assertSession()->fieldDisabled('No results behaviour');
    $this->assertSession()->fieldDisabled('More link');

    $this->assertSession()->fieldEnabled('The source translatable string');
    $this->assertSession()->fieldEnabled('The display translatable string');
    $this->assertSession()->fieldEnabled('Target');
    $this->assertSession()->fieldEnabled('More link label');
    $this->assertSession()->fieldEnabled('The message you want shown');
  }

  /**
   * Tests that we can unpublish link list translations independently.
   */
  public function testLinkListStatusTranslation(): void {
    // Create a link list with a FR translation.
    $link_list_storage = $this->container->get('entity_type.manager')->getStorage('link_list');
    $values = [
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list admin',
    ];

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->create($values);
    $configuration = [
      'source' => [
        'plugin' => 'test_cache_metadata',
        'plugin_configuration' => ['url' => 'http://example.com'],
      ],
      'display' => [
        'plugin' => 'test_configurable_title',
        'plugin_configuration' => ['link' => FALSE],
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->addTranslation('fr', $link_list->toArray());
    $link_list->save();

    // Assert both translations are published.
    $link_list = $this->getLinkListByTitle('My link list', TRUE);
    $this->assertEquals('en', $link_list->language()->getId());
    $this->assertTrue($link_list->isPublished());
    $this->assertTrue($this->container->get('content_translation.manager')->getTranslationMetadata($link_list)->isPublished());
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('fr', $translation->language()->getId());
    $this->assertTrue($translation->isPublished());
    $this->assertTrue($this->container->get('content_translation.manager')->getTranslationMetadata($translation)->isPublished());

    $french = \Drupal::languageManager()->getLanguage('fr');

    // Unpublish FR.
    $this->drupalGet($link_list->toUrl('edit-form', ['language' => $french]));
    $this->getSession()->getPage()->uncheckField('Published');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('My link list', TRUE);
    $this->assertEquals('en', $link_list->language()->getId());
    $this->assertTrue($link_list->isPublished());
    $this->assertTrue($this->container->get('content_translation.manager')->getTranslationMetadata($link_list)->isPublished());
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('fr', $translation->language()->getId());
    $this->assertFalse($translation->isPublished());
    $this->assertFalse($this->container->get('content_translation.manager')->getTranslationMetadata($translation)->isPublished());

    // Publish back FR and unpublish EN.
    $this->drupalGet($link_list->toUrl('edit-form', ['language' => $french]));
    $this->getSession()->getPage()->checkField('Published');
    $this->getSession()->getPage()->pressButton('Save');
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->getSession()->getPage()->uncheckField('Published');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('My link list', TRUE);
    $this->assertEquals('en', $link_list->language()->getId());
    $this->assertFalse($link_list->isPublished());
    $this->assertFalse($this->container->get('content_translation.manager')->getTranslationMetadata($link_list)->isPublished());
    $translation = $link_list->getTranslation('fr');
    $this->assertEquals('fr', $translation->language()->getId());
    $this->assertTrue($translation->isPublished());
    $this->assertTrue($this->container->get('content_translation.manager')->getTranslationMetadata($translation)->isPublished());
  }

}
