<?php

/**
 * @file
 * Post update functions for OpenEuropa RSS Link Lists module.
 */

declare(strict_types=1);

/**
 * Enable the multivalue_form_element module.
 */
function oe_link_lists_rss_source_post_update_00001() {
  \Drupal::service('module_installer')->install(['multivalue_form_element']);
}
