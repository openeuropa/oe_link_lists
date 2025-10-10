<?php

/**
 * @file
 * Post update functions for OE Link Lists Group.
 */

declare(strict_types=1);

use Drupal\group\Entity\GroupRelationshipType;

/**
 * Recalculate the dependencies for group_link_list group relationship types.
 */
function oe_link_lists_group_post_update_00001(): void {
  $relationship_types = GroupRelationshipType::loadByEntityTypeId('link_list');
  foreach ($relationship_types as $type) {
    $type->set('dependencies', [])->calculateDependencies()->save();
  }
}
