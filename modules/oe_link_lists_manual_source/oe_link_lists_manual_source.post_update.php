<?php

/**
 * @file
 * Post update functions for OpenEuropa Manual Link Lists module.
 */

declare(strict_types = 1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;

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

/**
 * Add inline entity reference removal policy to keep entities.
 */
function oe_link_lists_manual_source_post_update_removal_policy() {
  $form_display = EntityFormDisplay::load('link_list.manual.default');
  $component = $form_display->getComponent('links');
  $component['settings']['removed_reference'] = 'keep';
  $form_display->setComponent('links', $component);
  $form_display->save();

  // Make sure referenced entities are deleted once the host entities deleted.
  \Drupal::service('module_installer')->install(['composite_reference']);
  $field_config = FieldConfig::load('link_list.manual.links');
  $field_config->setThirdPartySetting('composite_reference', 'composite', TRUE);
  $field_config->save();
}
