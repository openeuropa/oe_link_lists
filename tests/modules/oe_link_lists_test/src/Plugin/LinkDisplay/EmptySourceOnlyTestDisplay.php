<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_empty_source_only_display",
 *   label = @Translation("Display for empty source"),
 *   description = @Translation("Display plugin only available for the empty source link source."),
 *   bundles = { "dynamic" },
 *   link_sources = { "test_empty_collection" }
 * )
 */
class EmptySourceOnlyTestDisplay extends GenericTestDisplayBase {}
