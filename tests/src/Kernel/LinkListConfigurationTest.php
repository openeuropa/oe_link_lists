<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the configuration of link lists.
 */
class LinkListConfigurationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'oe_link_lists_test',
    'user',
    'system',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('link_list');
    $this->installConfig([
      'oe_link_lists',
      'system',
      'language',
      'content_translation',
    ]);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'dynamic', TRUE);
  }

  /**
   * Tests that the link list configuration manager sets and gets correctly.
   */
  public function testLinkListConfigurationManager(): void {
    // Create a basic link list.
    $link_list_storage = \Drupal::entityTypeManager()->getStorage('link_list');
    $values = [
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'Link list 1',
    ];
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->create($values);

    // Add a translation and ensure the entity object is marked as having the
    // translation.
    $link_list->addTranslation('fr', $link_list->toArray());
    $this->assertTrue($link_list->hasTranslation('fr'));
    $this->assertEquals('fr', $link_list->getTranslation('fr')->language()->getId());

    // Create a standard link list configuration array.
    $configuration = [
      'source' => [
        'plugin' => 'foo',
        'plugin_configuration' => [
          'url' => 'http://google.com',
        ],
      ],
      'display' => [
        'plugin' => 'bar',
        'plugin_configuration' => [
          'link' => FALSE,
        ],
      ],
      'size' => 0,
      'more' => [
        'button' => 'no',
        // Does not matter what is under "target" as it's translatable as a
        // whole.
        'target' => [
          'test' => 'test',
        ],
      ],
    ];

    // Assert that the configuration is set and read in the exact same way.
    $link_list->setConfiguration($configuration);
    $this->assertEquals($configuration, $link_list->getConfiguration());

    $translated_configuration = $configuration;
    $this->translateConfiguration($translated_configuration);

    // Set the translated configuration on the translated list and assert that
    // only certain values have been translated.
    $translation = $link_list->getTranslation('fr');
    $translation->setConfiguration($translated_configuration);
    $expected = [
      'source' => [
        'plugin' => 'foo',
        'plugin_configuration' => [
          'url' => 'http://google.com',
        ],
      ],
      'display' => [
        'plugin' => 'bar',
        'plugin_configuration' => [
          'link' => FALSE,
        ],
      ],
      'size' => 0,
      'more' => [
        'button' => 'no',
        // Only the target was marked as translatable so it should change.
        'target' => [
          'test' => 'test FR',
        ],
      ],
    ];

    // Assert that the configuration gets merged correctly.
    $this->assertEquals($expected, $translation->getConfiguration());
    // Assert that the actual configuration value stored in the translation
    // contains only translatable keys.
    $expected_partial = [
      'more' => [
        'target' => [
          'test' => 'test FR',
        ],
      ],
    ];
    $this->assertEquals($expected_partial, $translation->get('configuration')->first()->getValue());

    // Add a source plugin that has a translatable configuration and set the
    // new configuration onto the original list.
    $configuration['source']['plugin'] = 'qux';
    $configuration['source']['plugin_configuration'] = [
      'my_string' => 'Original string',
    ];
    $link_list->setConfiguration($configuration);
    $this->assertEquals($configuration, $link_list->getConfiguration());

    // Translate this new configuration and set it on the translation.
    $translated_configuration = $configuration;
    $this->translateConfiguration($translated_configuration);
    $expected['source']['plugin'] = 'qux';
    $expected['source']['plugin_configuration'] = [
      // The source plugin configuration is translatable so it got translated.
      'my_string' => 'Original string FR',
    ];
    $translation->setConfiguration($translated_configuration);
    $this->assertEquals($expected, $translation->getConfiguration());

    // Assert that the actual configuration value stored in the translation
    // contains only translatable keys.
    $expected_partial = [
      'source' => [
        'plugin_configuration' => [
          'my_string' => 'Original string FR',
        ],
      ],
      'more' => [
        'target' => [
          'test' => 'test FR',
        ],
      ],
    ];
    $this->assertEquals($expected_partial, $translation->get('configuration')->first()->getValue());

    // Change the source plugin completely and ensure that the translatable
    // configuration of the previous plugin doesn't get wiped out.
    $link_list->removeTranslation('fr');
    $link_list->save();
    $this->assertFalse($link_list->hasTranslation('fr'));
    $translated_configuration = $configuration;
    // In the translation, we essentially force the change of the source plugin
    // (which should not be technically allowed) but ensure that as the source
    // plugin doesn't change, its configuration remains in place.
    $translated_configuration['source']['plugin'] = 'baz';
    $translated_configuration['source']['plugin_configuration'] = [];
    $translation = $link_list->addTranslation('fr');
    $translation->setConfiguration($translated_configuration);
    $expected_source = [
      'plugin' => 'qux',
      'plugin_configuration' => [
        // The original (translatable) string has been kept.
        'my_string' => 'Original string',
      ],
    ];
    $this->assertEquals($expected_source, $translation->getConfiguration()['source']);
  }

  /**
   * Translates an array of link list configuration.
   *
   * Runs through each value recursively and appends the "FR" string.
   *
   * @param array $configuration
   *   The configuration values.
   */
  protected function translateConfiguration(array &$configuration): void {
    foreach ($configuration as $key => &$value) {
      if (is_array($value)) {
        $this->translateConfiguration($value);
        continue;
      }

      if (is_bool($value)) {
        $value = !$value;
      }
      else {
        $value .= ' FR';
      }

    }
  }

}
