<?php

/**
 * @file
 * Post update functions for OE Link Lists.
 */

declare(strict_types = 1);

use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\oe_link_lists\Entity\LinkListType;

/**
 * Update all the bundles to use the link source plugin selection.
 */
function oe_link_lists_post_update_00001() {
  $link_list_types = LinkListType::loadMultiple();
  foreach ($link_list_types as $id => $link_list_type) {
    if ($id === 'manual') {
      // The manual one is handled in its own submodule.
      continue;
    }

    $link_list_type->set('configurable_link_source_plugins', TRUE);
    $link_list_type->save();
  }
}

/**
 * Update all link lists to set the default no_results_behaviour plugin.
 */
function oe_link_lists_post_update_00002(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Get all the link lists.
    $ids = \Drupal::entityTypeManager()
      ->getStorage('link_list')
      ->getQuery()
      ->execute();

    if (!$ids) {
      return t('No link lists need to be updated.');
    }

    $sandbox['ids'] = $ids;
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
    $sandbox['items_per_batch'] = 10;
  }

  $ids = array_slice($sandbox['ids'], $sandbox['current'], $sandbox['items_per_batch']);
  /** @var \Drupal\oe_link_lists\Entity\LinkListInterface[] $link_lists */
  $link_lists = LinkList::loadMultiple($ids);
  foreach ($link_lists as $link_list) {
    $configuration = $link_list->getConfiguration();
    if (isset($configuration['no_results_behaviour'])) {
      // This should not happen but in case something went wrong.
      $sandbox['current']++;
      continue;
    }
    $configuration['no_results_behaviour'] = [
      'plugin' => 'hide_list',
      'plugin_configuration' => [],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] === 1) {
    return t('A total of @updated link lists have been updated.', ['@updated' => $sandbox['current']]);
  }
}
