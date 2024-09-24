# OpenEuropa RSS Link Lists

This module provides support for dynamic link lists extracted from an RSS source. To this end, it uses the core Aggregator module to import the RSS feeds that will be displayed inside the link lists.

Beware that with the default configuration, Drupal discards entries older than 3 months. You should change that in case you are importing feed items that are older than that.

## Access

In order to allow users of the website to see the RSS feed items, you have to grant them the correct permission.
The Aggregator module contains only one permission for accessing feeds and items, namely `View news feeds`.
Granting this permission to users gives access to view the feed items but also exposes a set of routes related to the feed entity itself (which you may not want).

### Access permission-based solution

To solve this issue, we ship with an optional sub-module called "OpenEuropa Aggregator item access" (`oe_link_lists_aggregator_item_access`).\
It provides the permission `view feed items` which grants users the capability to view feed item entities.

## Installation

Before enabling this module, make sure the following dependencies are present in your codebase by adding them to your
`composer.json` and by running `composer update`:

```json
"require": {
    "drupal/multivalue_form_element": "^1",
}
```

The feed's URL field of RSS Link Source is limited to 2048 characters. For real usage, very long feed URLs are possible only with an update of the base field definition. You may do that by implementing this hook:
```php

/**
 * Implements hook_entity_base_field_info_alter().
 */
function HOOK_entity_base_field_info_alter(&$fields, EntityTypeInterface $entity_type) {
  if ($entity_type->id() !== 'aggregator_feed') {
    return;
  }

  $settings = $fields['title']->getItemDefinition()->getSettings();
  $settings['max_length'] = 2048;
  $fields['title']->getItemDefinition()->setSettings($settings);
}
```
and implement hook_install() in your custom module to update existing field definition like the following:
```php
/**
 * Update max length title field for aggregator feed entity type.
 */
function hook_install(): void {
  $database = \Drupal::database();
  $transaction = $database->startTransaction();

  $bundle_of = 'aggregator_feed';
  $id_key = 'fid';
  $table_name = 'aggregator_feed';
  $definition_manager = \Drupal::entityDefinitionUpdateManager();

  // Store the existing values.
  $status_values = $database->select($table_name)
    ->fields($table_name, [$id_key, 'title'])
    ->execute()
    ->fetchAllKeyed();

  // Clear out the values.
  $database->update($table_name)
    ->fields(['title' => NULL])
    ->execute();

  // Uninstall the field.
  $field_storage_definition = $definition_manager->getFieldStorageDefinition('title', $bundle_of);
  $definition_manager->uninstallFieldStorageDefinition($field_storage_definition);

  // Create a new field definition.
  $new_title_field = BaseFieldDefinition::create('string')
    ->setLabel(t('Title'))
    ->setDescription(t('The name of the feed (or the name of the website providing the feed).'))
    ->setRequired(TRUE)
    ->setSetting('max_length', 2048)
    ->setDisplayOptions('form', [
      'type' => 'string_textfield',
      'weight' => -5,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->addConstraint('FeedTitle');

  // Install the new definition.
  $definition_manager->installFieldStorageDefinition('title', $bundle_of, $bundle_of, $new_title_field);

  foreach ($status_values as $id => $value) {
    $database->update($table_name)
      ->fields(['title' => $value])
      ->condition($id_key, $id)
      ->execute();
  }

  // Commit transaction.
  unset($transaction);
}
```
