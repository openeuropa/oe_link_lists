<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_manual_source\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Base class for manual link list web tests.
 */
abstract class ManualLinkListTestBase extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'link',
    'oe_link_lists',
    'oe_link_lists_test',
    'oe_link_lists_manual_source',
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

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page 2',
      'body' => 'Page 2 body',
    ]);
  }

  /**
   * Creates an internal link in IEF.
   *
   * @param string $page
   *   The page number.
   * @param string|null $title
   *   The title.
   * @param string|null $teaser
   *   The teaser.
   */
  protected function createInlineInternalLink(string $page, ?string $title = NULL, ?string $teaser = NULL): void {
    $this->getSession()->getPage()->selectFieldOption('links[actions][bundle]', 'internal');
    $this->getSession()->getPage()->pressButton('Add new Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertFalse($this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex .field--name-title')->isVisible());
    $this->assertFalse($this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex .field--name-teaser')->isVisible());
    $links_wrapper->fillField('Target', "Page $page ($page)");
    if ($title || $teaser) {
      $links_wrapper->checkField('Override target values');
      if ($title) {
        $links_wrapper->fillField('Title', $title);
      }
      if ($teaser) {
        $links_wrapper->fillField('Teaser', $teaser);
      }
    }
    $this->getSession()->getPage()->pressButton('Create Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains("Internal link to: Page $page");
  }

  /**
   * Creates an external link in IEF.
   *
   * @param string $url
   *   The URL.
   * @param string|null $title
   *   The title.
   * @param string|null $teaser
   *   The teaser.
   */
  protected function createInlineExternalLink(string $url, string $title, string $teaser): void {
    $this->getSession()->getPage()->selectFieldOption('links[actions][bundle]', 'external');
    $this->getSession()->getPage()->pressButton('Add new Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertNotNull($links_wrapper);
    $links_wrapper->fillField('URL', $url);
    $links_wrapper->fillField('Title', $title);
    $links_wrapper->fillField('Teaser', $teaser);
    $this->getSession()->getPage()->pressButton('Create Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('External link to: ' . $url);
  }

  /**
   * Uses the link source plugin to load the links of a given list.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return \Drupal\oe_link_lists\LinkInterface[]
   *   The links.
   */
  protected function getLinksFromList(LinkListInterface $link_list): array {
    $configuration = $link_list->getConfiguration();
    /** @var \Drupal\oe_link_lists\LinkSourceInterface $plugin */
    $plugin = \Drupal::service('plugin.manager.oe_link_lists.link_source')->createInstance($configuration['source']['plugin'], $configuration['source']['plugin_configuration']);
    return $plugin->getLinks()->toArray();
  }

}
