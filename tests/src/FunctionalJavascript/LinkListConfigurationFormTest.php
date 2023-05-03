<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\aggregator\FeedStorageInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_link_lists\Traits\NativeBrowserValidationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the link list form.
 *
 * @group oe_link_lists
 */
class LinkListConfigurationFormTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use NativeBrowserValidationTrait;
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
    'oe_link_lists',
    'oe_link_lists_manual_source',
    'oe_link_lists_rss_source',
    'oe_link_lists_test',
    'node',
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

    // Do not delete old aggregator items during these tests, since our sample
    // feeds have hardcoded dates in them (which may be expired when this test
    // is run).
    $this->config('aggregator.settings')->set('items.expire', FeedStorageInterface::CLEAR_NEVER)->save();

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

    $web_user = $this->drupalCreateUser([
      'create dynamic link list',
      'edit dynamic link list',
      'create foo link list',
      'create single_plugin link list',
      'edit foo link list',
      'edit single_plugin link list',
      'view link list',
      'access link list canonical page',
    ]);
    $this->drupalLogin($web_user);
  }

  /**
   * Tests that a link display can be configured.
   */
  public function testLinkListDisplayConfiguration(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('link_list');

    $this->drupalGet('link_list/add/single_plugin');
    // Assert we can see only the source plugins that have no bundle
    // restrictions.
    $this->assertFieldSelectOptions('Link source', [
      'test_no_bundle_restriction_source',
    ]);
    // Assert that since we have only 1 available source, it is by default
    // selected.
    $this->assertEquals('selected', $this->assertSession()->selectExists('Link source')->find('css', 'option[value="test_no_bundle_restriction_source"]')->getAttribute('selected'));
    // Assert we can see only the display plugins that have no bundle
    // restrictions.
    $this->assertFieldSelectOptions('Link display', [
      'test_no_bundle_restriction_display',
    ]);
    // Assert that since we have only 1 available display, it is by default
    // selected.
    $this->assertEquals('selected', $this->assertSession()->selectExists('Link display')->find('css', 'option[value="test_no_bundle_restriction_display"]')->getAttribute('selected'));

    $this->drupalGet('link_list/add/foo');
    // Assert we can only see the source plugins that work with the Foo
    // bundle (or that don't have a bundle restriction).
    $this->assertFieldSelectOptions('Link source', [
      'test_foo_bundle_only_source',
      'test_no_bundle_restriction_source',
    ]);

    // Assert we can only see the display plugins that work with the Foo
    // bundle (or that don't have a bundle restriction).
    $this->assertFieldSelectOptions('Link display', [
      'test_foo_bundle_display',
      'test_no_bundle_restriction_display',
    ]);

    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'The admin title');
    $this->getSession()->getPage()->fillField('Title', 'The title');
    $this->assertSession()->selectExists('Link source');

    // Assert we can only see the source plugins that work with the Dynamic
    // bundle.
    $this->assertFieldSelectOptions('Link source', [
      'configurable_non_translatable_test_source',
      'rss_links',
      'same_configuration_source_one',
      'same_configuration_source_two',
      'test_cache_metadata',
      'test_complex_form',
      'test_empty_collection',
      'test_empty_collection_with_cache',
      'test_example_source',
      'test_translatable',
      'test_no_bundle_restriction_source',
    ]);

    // Assert we can only see the display plugins that work with the Dynamic
    // bundle.
    $this->assertFieldSelectOptions('Link display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);

    // Pick a source plugin that will allow another display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Empty collection');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFieldSelectOptions('Link display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_empty_source_only_display',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);

    // Select the display plugin that has been just made available.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Display for empty source');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Change to another source plugin to test the available display plugins
    // reflect this.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'RSS');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEmpty($this->getSession()->getPage()->findField('Link display')->find('css', "option[selected=selected]"));
    $this->assertFieldSelectOptions('Link display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);
    $this->assertSession()->fieldExists('The resource URL');
    $this->getSession()->getPage()->fillField('The resource URL', 'http://www.example.com/atom.xml');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');

    // Select and configure the no results behaviour plugin.
    $this->assertFieldSelectOptions('No results behaviour', [
      'hide_list',
      'non_translatable_text_message',
      'text_message',
      'same_configuration_no_results_one',
      'same_configuration_no_results_two',
    ]);
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('test_link_tag', $configuration['display']['plugin']);
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
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Titles with optional link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->checkboxChecked('Link');
    $this->getSession()->getPage()->uncheckField('Link');
    $this->getSession()->getPage()->pressButton('Save');

    $storage->resetCache();
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('test_configurable_title', $configuration['display']['plugin']);
    $this->assertEquals([
      'link' => FALSE,
    ], $configuration['display']['plugin_configuration']);
  }

  /**
   * Tests more scenarios for display plugins depending on source plugins.
   */
  public function testDisplaySourceDependencies(): void {
    // Limit sources to only one, so that it will be automatically selected in
    // the form.
    \Drupal::state()->set('oe_link_lists_test_allowed_sources', ['test_empty_collection']);

    $this->drupalGet('link_list/add/dynamic');
    $this->assertFieldSelectOptions('Link source', [
      'test_empty_collection',
    ]);

    // The "test_empty_source_only_display" should be available, as it can
    // be used with the source selected above.
    $this->assertFieldSelectOptions('Link display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_empty_source_only_display',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = \Drupal::entityTypeManager()->getStorage('link_list')->create([
      'bundle' => 'dynamic',
      'administrative_title' => $this->randomMachineName(),
    ]);
    $configuration = [
      'source' => [
        'plugin' => 'test_empty_collection',
        'plugin_configuration' => ['url' => 'http://example.com'],
      ],
      'display' => [
        'plugin' => 'title',
        'plugin_configuration' => [],
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    // Test that link display filtering is executed on first load of a link list
    // edit form.
    $this->drupalGet($link_list->toUrl('edit-form'));
    // Again the "test_empty_source_only_display" should be available.
    $this->assertFieldSelectOptions('Link display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_empty_source_only_display',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);
  }

  /**
   * Tests that a list can have a limit and a "More link".
   */
  public function testLinkListMoreLink(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'The admin title');
    $this->getSession()->getPage()->fillField('Title', 'The title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');

    // Check that the Size field exists.
    $select = $this->assertSession()->selectExists('Number of items');
    // 20 items are selected by default.
    $this->assertEquals(20, $select->getValue());

    // Show all links.
    $this->getSession()->getPage()->selectFieldOption('Number of items', 0);

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    // Both test links should show.
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkExists('DIGIT');
    // There should be no "See all".
    $this->assertSession()->linkNotExists('See all');

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEmpty($link_list->getConfiguration()['more_link']);

    // Show only 2 links with no "See all" button.
    $this->drupalGet('link_list/1/edit');
    $this->assertFalse($this->assertSession()->selectExists('More link')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Number of items', 2);
    $this->assertTrue($this->assertSession()->selectExists('More link')->isVisible());
    $this->assertFieldSelectOptions('More link', [
      'configurable_non_translatable_link',
      'custom_link',
      'hardcoded_link',
      'same_configuration_more_link_one',
      'same_configuration_more_link_two',
    ]);
    // No more_link plugin is selected.
    $this->assertEquals('selected', $this->assertSession()->selectExists('More link')->find('css', 'option[value=""]')->getAttribute('selected'));
    $this->assertSession()->fieldNotExists('Target');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkNotExists('DIGIT');
    $this->assertSession()->linkNotExists('See all');

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEmpty($link_list->getConfiguration()['more_link']);

    // Add an external "More link" with the default label.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('More link', 'Custom link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The custom_link plugin is now selected so we can see its fields.
    $this->assertSession()->fieldExists('Target');
    $this->assertSession()->fieldExists('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->assertSession()->checkboxNotChecked('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->assertFalse($this->assertSession()->fieldExists('More link label')->isVisible());

    // Verify that the target field is required.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The path is invalid.');
    $this->getSession()->getPage()->fillField('Target', 'httq://example.com/more-link');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The path httq://example.com/more-link is invalid.');
    $this->getSession()->getPage()->fillField('Target', 'fake:url');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The path fake:url is invalid.');
    // Add a proper target value.
    $this->getSession()->getPage()->fillField('Target', 'http://example.com/more-link');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkNotExists('DIGIT');
    $this->assertSession()->linkExists('See all');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEquals([
      'plugin' => 'custom_link',
      'plugin_configuration' => [
        'target' => [
          'type' => 'custom',
          'url' => 'http://example.com/more-link',
        ],
        'title_override' => NULL,
      ],
    ], $link_list->getConfiguration()['more_link']);

    $this->drupalGet('link_list/1/edit');
    // Switch the link source plugin to check the more link form still shows
    // correctly.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Empty collection');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('Target');
    $this->assertSession()->fieldExists('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->assertSession()->checkboxNotChecked('Override the link label. Defaults to "See all" or the referenced entity label.');

    // Switch back the link source.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Specify a custom label for the "More link".
    $this->getSession()->getPage()->checkField('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->assertTrue($this->assertSession()->fieldExists('More link label')->isVisible());
    // Verify that the target field is required when the "more link label"
    // checkbox is selected.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The "More link" label is required if you want to override the "More link" title.');

    // Set a proper "More link" label override.
    $this->getSession()->getPage()->fillField('More link label', 'Custom more button');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('See all');
    $this->assertSession()->linkExists('Custom more button');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEquals([
      'plugin' => 'custom_link',
      'plugin_configuration' => [
        'target' => [
          'type' => 'custom',
          'url' => 'http://example.com/more-link',
        ],
        'title_override' => 'Custom more button',
      ],
    ], $link_list->getConfiguration()['more_link']);

    // Verify that strings that can be cast to false are rendered.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->fillField('More link label', '0');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('See all');
    $this->assertSession()->linkExists('0');
    $this->assertSession()->linkByHrefExists('http://example.com/more-link');

    // Create some nodes.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['title' => 'Dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in aliqu']);
    $this->drupalCreateNode(['title' => 'Page 2']);

    // Change the "More link" to a local Node, with the custom label.
    $this->drupalGet('link_list/1/edit');
    $target_field = $this->assertSession()->waitForField('Target');
    $target_field->setValue('Dolor');
    // The autocomplete list is shown on key down event.
    $this->getSession()->getDriver()->keyDown($target_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();
    // Pick the node with the long title from the list.
    $this->getSession()->getPage()
      ->find('css', '.ui-autocomplete')
      ->find('xpath', '//a[.="Dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in aliqu"]')
      ->click();
    $this->assertSession()->fieldValueEquals('Target', "{$node->label()} ({$node->id()})");
    $this->getSession()->getPage()->fillField('More link label', 'Custom more button');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('Custom more button');
    $this->assertSession()->linkByHrefNotExists('http://example.com/more-link');
    $this->assertSession()->linkByHrefExists($node->toUrl()->toString());

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEquals([
      'plugin' => 'custom_link',
      'plugin_configuration' => [
        'target' => [
          'type' => 'entity',
          'entity_type' => 'node',
          'entity_id' => $node->id(),
        ],
        'title_override' => 'Custom more button',
      ],
    ], $link_list->getConfiguration()['more_link']);

    // Point to a non-existing node.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->fillField('Target', 'Non existing (300)');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The referenced entity (node: 300) does not exist.');

    // Remove the title override for the "More link".
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->uncheckField('Override the link label. Defaults to "See all" or the referenced entity label.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkNotExists('Custom more button');
    // The default More link label is shown.
    $this->assertSession()->linkExists($node->label());
    $this->assertSession()->linkByHrefExists($node->toUrl()->toString());

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEquals([
      'plugin' => 'custom_link',
      'plugin_configuration' => [
        'target' => [
          'type' => 'entity',
          'entity_type' => 'node',
          'entity_id' => $node->id(),
        ],
        'title_override' => NULL,
      ],
    ], $link_list->getConfiguration()['more_link']);

    // Remove node used in the "More link".
    $node->delete();
    $this->getSession()->reload();
    $this->assertSession()->linkExists('Example');
    $this->assertSession()->linkExists('European Commission');
    $this->assertSession()->linkByHrefNotExists($node->toUrl()->toString());

    // Change the "More link" plugin.
    $this->drupalGet('link_list/1/edit');
    $this->getSession()->getPage()->selectFieldOption('More link', 'Hardcoded link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->linkExists('A harcoded link');
    $this->assertSession()->linkByHrefExists('http://europa.eu');

    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->assertEquals([
      'plugin' => 'hardcoded_link',
      'plugin_configuration' => [],
    ], $link_list->getConfiguration()['more_link']);
  }

  /**
   * Tests that deprecated link source plugins cannot be used.
   */
  public function testDeprecatedLinkSourcePlugins(): void {
    // When creating a new link list, the deprecated plugin doesn't show.
    $this->drupalGet('link_list/add/dynamic');
    $this->assertFieldSelectOptions('Link source', [
      'configurable_non_translatable_test_source',
      'rss_links',
      'same_configuration_source_one',
      'same_configuration_source_two',
      'test_cache_metadata',
      'test_complex_form',
      'test_empty_collection',
      'test_empty_collection_with_cache',
      'test_example_source',
      'test_translatable',
      'test_no_bundle_restriction_source',
    ]);

    // If we do have an existing link list using a now-deprecated plugin, the
    // edit form will still show it.
    $link_list_storage = \Drupal::entityTypeManager()->getStorage('link_list');
    $values = [
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ];
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->create($values);
    $configuration = [
      'source' => [
        'plugin' => 'test_deprecated_source',
        'plugin_configuration' => [],
      ],
      'display' => [
        'plugin' => 'test_no_bundle_restriction_display',
        'plugin_configuration' => [],
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
      'size' => 0,
      'more' => [],
    ];

    // Assert that the configuration is set and read in the exact same way.
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertFieldSelectOptions('Link source', [
      'configurable_non_translatable_test_source',
      'rss_links',
      'same_configuration_source_one',
      'same_configuration_source_two',
      'test_cache_metadata',
      'test_complex_form',
      'test_deprecated_source',
      'test_empty_collection',
      'test_empty_collection_with_cache',
      'test_example_source',
      'test_translatable',
      'test_no_bundle_restriction_source',
    ]);

    // It's possible to save the link list and use it with the deprecated
    // plugin.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Link list 1 Link list.');

    // We can edit again and change the link source to use a supported on.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Link list 1 Link list.');

    $link_list = $this->getLinkListByTitle('My link list', TRUE);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('test_example_source', $configuration['source']['plugin']);

    // If we edit it again, we won't see the deprecated link source option
    // anymore.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertFieldSelectOptions('Link source', [
      'configurable_non_translatable_test_source',
      'rss_links',
      'same_configuration_source_one',
      'same_configuration_source_two',
      'test_cache_metadata',
      'test_complex_form',
      'test_empty_collection',
      'test_empty_collection_with_cache',
      'test_example_source',
      'test_translatable',
      'test_no_bundle_restriction_source',
    ]);
  }

  /**
   * Tests the AJAX selected plugins get fresh configuration.
   *
   * This ensures that when a user changes the plugin in the UI, the newsly
   * instantiated one doesn't get configuration from the previous one.
   */
  public function testNewSelectedPluginConfiguration(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Administrative title', 'The admin title');
    $this->getSession()->getPage()->fillField('Title', 'The title');

    // Select and configure the source plugin.
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Same configuration source one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('configuration[0][link_source][plugin_configuration_wrapper][same_configuration_source_one][value]', 'A simple value');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Same configuration display one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('configuration[0][link_display][plugin_configuration_wrapper][same_configuration_display_one][value]', 'A simple value');

    // Select and configure the more link plugin.
    $this->getSession()->getPage()->selectFieldOption('More link', 'Same configuration more_link one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('configuration[0][link_display][more_link][plugin_configuration_wrapper][same_configuration_more_link_one][value]', 'A simple value');

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Same configuration no_results one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('configuration[0][no_results_behaviour][plugin_configuration_wrapper][same_configuration_no_results_one][value]', 'A simple value');

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Save');

    // Edit the link list and change the plugins with the ones that have the
    // same configuration keys.
    $link_list = $this->getLinkListByTitle('The title', TRUE);
    $this->drupalGet($link_list->toUrl('edit-form'));

    $this->getSession()->getPage()->selectFieldOption('Link source', 'Same configuration source two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals('', $this->getSession()->getPage()->findField('configuration[0][link_source][plugin_configuration_wrapper][same_configuration_source_two][value]')->getValue());

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Same configuration display two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals('', $this->getSession()->getPage()->findField('configuration[0][link_display][plugin_configuration_wrapper][same_configuration_display_two][value]')->getValue());

    $this->getSession()->getPage()->selectFieldOption('More link', 'Same configuration more_link two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals('', $this->getSession()->getPage()->findField('configuration[0][link_display][more_link][plugin_configuration_wrapper][same_configuration_more_link_two][value]')->getValue());

    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Same configuration no_results two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals('', $this->getSession()->getPage()->findField('configuration[0][no_results_behaviour][plugin_configuration_wrapper][same_configuration_no_results_two][value]')->getValue());
  }

  /**
   * Checks if a select element contains the specified options.
   *
   * @param string $name
   *   The field name.
   * @param array $expected_options
   *   An array of expected options.
   */
  protected function assertFieldSelectOptions(string $name, array $expected_options): void {
    $select = $this->getSession()->getPage()->find('named', [
      'select',
      $name,
    ]);

    if (!$select) {
      $this->fail('Unable to find select ' . $name);
    }

    $options = $select->findAll('css', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getValue();
    });
    $options = array_filter($options);
    sort($options);
    sort($expected_options);
    $this->assertSame($expected_options, $options);
  }

}
