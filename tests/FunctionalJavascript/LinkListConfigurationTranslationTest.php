<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Tests the link list form configuration translation.
 *
 * @group oe_link_lists
 */
class LinkListConfigurationTranslationTest extends WebDriverTestBase {

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
  protected function setUp() {
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
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Complex Form Source');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The source translatable string', 'I can be translated');
    $this->getSession()->getPage()->fillField('The source non translatable string', 'I cannot be translated');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Translatable form display');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('The display translatable string', 'I can be translated');

    // Configure the non-plugin configuration options.
    $this->getSession()->getPage()->selectFieldOption('Number of items', 2);
    $this->getSession()->getPage()->findField('Yes, display a custom button')->click();
    $this->getSession()->getPage()->fillField('Target', 'http://example.com/more-link');
    $this->getSession()->getPage()->checkField('Override the button label. Defaults to "See all" or the referenced entity label.');
    $this->getSession()->getPage()->fillField('Button label', 'Custom more button');

    $this->getSession()->getPage()->pressButton('Save');

    // Try to translate the list.
    $link_list = $this->getLinkListByTitle('Title test');
    $url = $link_list->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'fr');
    $this->drupalGet($url);

    // Assert that all form elements that are not translatable are disabled.
    $this->assertSession()->fieldDisabled('Link source');
    $this->assertSession()->fieldDisabled('The source non translatable string');
    $this->assertSession()->fieldDisabled('Link display');
    $this->assertSession()->fieldDisabled('Number of items');
    $this->assertSession()->fieldDisabled('configuration[0][link_display][more][button]');
    $this->assertSession()->fieldDisabled('configuration[0][link_display][more][more_title_override]');

    $this->assertSession()->fieldEnabled('The source translatable string');
    $this->assertSession()->fieldEnabled('The display translatable string');
    $this->assertSession()->fieldEnabled('Target');
    $this->assertSession()->fieldEnabled('Button label');
  }

}
