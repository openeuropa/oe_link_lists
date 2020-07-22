<?php

/**
 * @file
 * Post update functions for OpenEuropa Manual Link Lists module.
 */

declare(strict_types = 1);

/**
 * Add override third party setting internal bundle.
 */
function oe_link_lists_manual_source_post_update_override() {
  $link_list_link_type_storage = \Drupal::entityTypeManager()->getStorage('link_list_link_type');
  $internal = $link_list_link_type_storage->load('internal');
  // Set override true for internal bundle.
  $internal->set('third_party_settings', [
    'oe_link_lists_manual_source' => [
      'override' => TRUE,
    ],
  ]);
  $internal->save();
}
