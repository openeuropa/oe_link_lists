<?php

/**
 * @file
 * Post update functions for OE Link Lists.
 */

declare(strict_types = 1);

use Drupal\oe_link_lists\Entity\LinkListType;

/**
 * Update all the bundles to use the link source plugin selection.
 */
function oe_link_lists_post_update_0001() {
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
