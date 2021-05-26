<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_foo_bundle_display",
 *   label = @Translation("Display for foo bundle"),
 *   description = @Translation("Display plugin only available for the foo bundle."),
 *   bundles = { "foo" }
 * )
 */
class FooBundleOnlyTestDisplay extends GenericTestDisplayBase {}
