<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;
use Drupal\oe_link_lists_test\SameConfigurationPluginTrait;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "same_configuration_display_two",
 *   label = @Translation("Same configuration display two."),
 *   description = @Translation("Same configuration display two."),
 *   bundles = { "dynamic" }
 * )
 */
class SameConfigurationDisplayTwo extends LinkDisplayPluginBase {

  use SameConfigurationPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    return [];
  }

}
