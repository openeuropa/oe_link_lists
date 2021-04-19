<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_no_bundle_restriction_display",
 *   label = @Translation("Display without bundle restriction"),
 *   description = @Translation("Link display available on any bundle"),
 * )
 */
class NoBundleRestrictionTestDisplay extends GenericTestDisplayBase {}
