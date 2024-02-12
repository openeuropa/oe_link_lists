<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_link_tag",
 *   label = @Translation("Links"),
 *   description = @Translation("Renders the collection as link tags."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class LinkTagTestDisplay extends GenericTestDisplayBase {}
