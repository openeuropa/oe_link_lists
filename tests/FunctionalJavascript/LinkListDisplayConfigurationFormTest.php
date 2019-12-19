<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\FunctionalJavascript;

use Drupal\aggregator\FeedStorageInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\oe_link_lists\Traits\NativeBrowserValidationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the link list form.
 *
 * @group oe_link_lists
 */
class LinkListDisplayConfigurationFormTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use NativeBrowserValidationTrait;

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
    'oe_link_lists',
    'oe_link_lists_manual_source',
    'oe_link_lists_rss_source',
    'oe_link_lists_test',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

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
            $filename = 'aggregator_test_atom.xml';
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

    $web_user = $this->drupalCreateUser(['administer link_lists']);
    $this->drupalLogin($web_user);
  }

  /**
   * Tests that a link display can be configured.
   */
  public function testLinkListDisplayConfiguration(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');

    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'The admin title');
    $this->getSession()->getPage()->fillField('Title', 'The title');
    $this->assertSession()->selectExists('Link source');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');

    // Select and configure the source plugin. We use the RSS plugin for this
    // test.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'RSS');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('The resource URL');
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/atom.xml');

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('foo', $configuration['display']['plugin']);
    $this->assertEquals(['title' => NULL, 'more' => []], $configuration['display']['plugin_configuration']);

    // Change the Source plugin to none.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('Link source', 'None');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'Link source field is required.');

    // Change the display plugin to none.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'None');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'Link display field is required.');

    // Change the display plugin to make it configurable.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Bar');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->checkboxChecked('Link');
    $this->getSession()->getPage()->uncheckField('Link');
    $this->getSession()->getPage()->pressButton('Save');

    $storage->resetCache();
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('bar', $configuration['display']['plugin']);
    $this->assertEquals([
      'link' => FALSE,
    ], $configuration['display']['plugin_configuration']);
  }

  /**
   * Tests that a list can have a limit and a "See all" button.
   */
  public function testLinkListGeneralConfiguration(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'The admin title');
    $this->getSession()->getPage()->fillField('Title', 'The title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');

    // Check that the Size field exists.
    $select = $this->assertSession()->selectExists('Number of items');
    $this->assertEquals(0, $select->getValue());
    $this->assertSession()->pageTextNotContains('Display link to see all');

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Bat');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    // Both test links should show.
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkExists('DIGIT');

    // There should be no "See all".
    $this->assertSession()->linkNotExists('See all');

    // Show only 2 links with no "See all" button.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('Number of items', 2);
    $this->assertSession()->pageTextContains('Display button to see all links');
    $this->assertSession()->checkboxChecked('No, do not display "See all" button');
    $this->assertSession()->pageTextNotContains('Target');
    $this->assertFalse($this->assertSession()->fieldExists('Target')->isVisible());
    $this->assertFalse($this->assertSession()->fieldExists('Override the button label. Defaults to "See all" or the referenced entity label.')->isVisible());
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkNotExists('DIGIT');
    $this->assertSession()->linkNotExists('See all');

    // Add a "See all" external button with the default label.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->findField('Yes, display a custom button')->click();
    $this->assertTrue($this->assertSession()->fieldExists('Target')->isVisible());
    $this->assertTrue($this->assertSession()->fieldExists('Override the button label. Defaults to "See all" or the referenced entity label.')->isVisible());
    $this->assertSession()->checkboxNotChecked('Override the button label. Defaults to "See all" or the referenced entity label.');
    $this->assertFalse($this->assertSession()->fieldExists('Button label')->isVisible());

    // Verify that the target field is required when the "display custom button"
    // option is selected.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The target is required if you want to override the "See all" button.');
    $this->getSession()->getPage()->fillField('Target', 'httq://example.com/more-link');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The path httq://example.com/more-link is invalid.');
    $this->getSession()->getPage()->fillField('Target', 'fake:url');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The path fake:url is invalid.');

    $this->getSession()->getPage()->fillField('Target', 'http://example.com/more-link');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkNotExists('DIGIT');
    $this->assertSession()->linkExists('See all');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    // Specify a custom label for the "See all button".
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->checkField('Override the button label. Defaults to "See all" or the referenced entity label.');
    $this->assertTrue($this->assertSession()->fieldExists('Button label')->isVisible());
    // Verify that the target field is required when the "override button label"
    // checkbox is selected.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The button label is required if you want to override the "See all" button title.');

    $this->getSession()->getPage()->fillField('Button label', 'Custom more button');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('See all');
    $this->assertSession()->linkExists('Custom more button');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    // Verify that strings that can be casted to false are rendered.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->fillField('Button label', '0');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('See all');
    $this->assertSession()->linkExists('0');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    // Create some nodes.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['title' => 'Page 1']);
    $this->drupalCreateNode(['title' => 'Page 2']);

    // Change the "See all" button to a local Node, with the custom label.
    $this->drupalGet('link_list/1/edit');
    $target_field = $this->assertSession()->waitForField('Target');
    $target_field->setValue('Page');
    // The autocomplete list is shown on key down event.
    $this->getSession()->getDriver()->keyDown($target_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();
    // Pick the "Page 1" option from the list.
    $this->getSession()->getPage()
      ->find('css', '.ui-autocomplete')
      ->find('xpath', '//a[.="Page 1"]')
      ->click();
    $this->assertSession()->fieldValueEquals('Target', "{$node->label()} ({$node->id()})");
    $this->getSession()->getPage()->fillField('Button label', 'Custom more button');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Custom more button');
    $this->assertSession()->linkByHrefNotExists('http://example.com/more-link');
    $this->assertSession()->linkByHrefExists($node->toUrl()->toString());

    // Point to a non-existing node.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->fillField('Target', 'Non existing (300)');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The referenced entity (node: 300) does not exist.');

    // Remove the title override for the "See all" button.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->uncheckField('Override the button label. Defaults to "See all" or the referenced entity label.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('Custom more button');
    // The default button label is shown.
    $this->assertSession()->linkExists($node->label());
    $this->assertSession()->linkByHrefExists($node->toUrl()->toString());
  }

}
