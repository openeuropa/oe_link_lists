<?php

namespace Drupal\oe_link_lists;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem;

/**
 * Manages the setting an getting the configuration out of the link list.
 */
class LinkListConfigurationManager {

  /**
   * The link source manager.
   *
   * @var \Drupal\oe_link_lists\LinkSourcePluginManagerInterface
   */
  protected $linkSourceManager;

  /**
   * The link display manager.
   *
   * @var \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface
   */
  protected $linkDisplayManager;

  /**
   * LinkListConfigurationManager constructor.
   *
   * @param \Drupal\oe_link_lists\LinkSourcePluginManagerInterface $linkSourceManager
   *   The link source manager.
   * @param \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface $linkDisplayManager
   *   The link display manager.
   */
  public function __construct(LinkSourcePluginManagerInterface $linkSourceManager, LinkDisplayPluginManagerInterface $linkDisplayManager) {
    $this->linkSourceManager = $linkSourceManager;
    $this->linkDisplayManager = $linkDisplayManager;
  }

  /**
   * Sets the configuration on the field item.
   *
   * If the entity onto which we are setting the configuration is the original,
   * untranslated value, we set the configuration as it comes. If, however,
   * it is a translation, we only set the values for the configuration that
   * have been marked as translatable.
   *
   * @param array $configuration
   *   The configuration.
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   The individual field item.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The updated field item.
   */
  public function setConfiguration(array $configuration, LinkListConfigurationItem $item): FieldItemInterface {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $item->getEntity();

    if ($link_list->isDefaultTranslation()) {
      // If we are setting the configuration on the original, untranslated
      // entity, we set the entire value and return.
      $item->setValue($configuration);
      return $item;
    }

    // We are working on a translation, so we need to only save the
    // configuration values that are marked as translatable.
    $translated_configuration = $this->mergeConfigurationValues($item, [], $configuration);
    $item->setValue($translated_configuration);

    return $item;
  }

  /**
   * Gets the configuration from the field item.
   *
   * When retrieving the configuration values for translations of the list,
   * we merge the untranslated values with the ones stored in the translation
   * of the field.
   *
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   The individual field item.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration(LinkListConfigurationItem $item): array {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $item->getEntity();

    /** @var \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $untranslated_item */
    $untranslated_item = $this->getUntranslatedFieldItem($item);
    $untranslated = $this->extractConfiguration($untranslated_item);

    if ($link_list->isDefaultTranslation()) {
      return $untranslated;
    }

    $translated = $this->extractConfiguration($item);
    $configuration = $this->mergeConfigurationValues($item, $untranslated, $translated);

    return $configuration;
  }

  /**
   * Extracts the configuration from the field item.
   *
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   The field item.
   *
   * @return array
   *   The configuration values.
   */
  protected function extractConfiguration(LinkListConfigurationItem $item): array {
    return !$item->isEmpty() ? $item->getValue() : [];
  }

  /**
   * Returns the list of parents for the translatable values.
   *
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   The configuration field item.
   *
   * @return array
   *   The list of parents.
   */
  public function getTranslatableParents(LinkListConfigurationItem $item) {
    // We start by adding the values that are not provided by plugins.
    $parents = [
      'link_display][more][more_title' => ['more', 'title_override'],
      'link_display][more][more_target' => ['more', 'target'],
    ];

    $configuration = $this->extractConfiguration($this->getUntranslatedFieldItem($item));

    // Then we load all the plugins and ask for their parents.
    $source_plugin = isset($configuration['source']['plugin']) ? $this->linkSourceManager->createInstance($configuration['source']['plugin']) : NULL;
    if ($source_plugin instanceof TranslatableLinkListPluginInterface) {
      $plugin_parents = $this->getPluginTranslatableParents($source_plugin, ['source', 'plugin_configuration']);
      $parents = array_merge($parents, $plugin_parents);
    }
    $display_plugin = isset($configuration['display']['plugin']) ? $this->linkDisplayManager->createInstance($configuration['display']['plugin']) : NULL;
    if ($display_plugin instanceof TranslatableLinkListPluginInterface) {
      $plugin_parents = $this->getPluginTranslatableParents($display_plugin, ['display', 'plugin_configuration']);
      $parents = array_merge($parents, $plugin_parents);
    }

    return $parents;
  }

  /**
   * Creates the list of parents from the plugins that provide them.
   *
   * @param \Drupal\oe_link_lists\TranslatableLinkListPluginInterface $plugin
   *   The plugin that can provide translatable parents.
   * @param array $base_parents
   *   The parents under the main configuration schema to append to the plugin
   *   specific ones.
   *
   * @return array
   *   The parents.
   */
  protected function getPluginTranslatableParents(TranslatableLinkListPluginInterface $plugin, array $base_parents = []): array {
    $parents = [];

    $plugin_parents = $plugin->getTranslatableParents();
    foreach ($plugin_parents as $plugin_parent_set) {
      $parents[implode('][', $plugin_parent_set)] = array_merge($base_parents, $plugin_parent_set);
    }

    return $parents;
  }

  /**
   * Merges the translated values into the main configuration array.
   *
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   The configuration field item.
   * @param array $configuration
   *   The main configuration values.
   * @param array $translated
   *   The translated values.
   *
   * @return array
   *   The merged configuration array.
   */
  protected function mergeConfigurationValues(LinkListConfigurationItem $item, array $configuration, array $translated): array {
    $translatable_parents = $this->getTranslatableParents($item);
    foreach ($translatable_parents as $parents) {
      $translated_value = NestedArray::getValue($translated, $parents, $key_exists);
      if ($key_exists) {
        NestedArray::setValue($configuration, $parents, $translated_value);
      }
    }

    return $configuration;
  }

  /**
   * Returns the untranslated configuration field item.
   *
   * @param \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item
   *   Translated (or otherwise) configuration field item.
   *
   * @return \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem
   *   The field item.
   */
  protected function getUntranslatedFieldItem(LinkListConfigurationItem $item): LinkListConfigurationItem {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $item->getEntity();

    $field_name = $item->getFieldDefinition()->getName();
    $delta = $item->getName();

    /** @var \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $untranslated_item */
    $untranslated_item = $link_list->getUntranslated()->get($field_name)->get($delta);

    return $untranslated_item;
  }

}
