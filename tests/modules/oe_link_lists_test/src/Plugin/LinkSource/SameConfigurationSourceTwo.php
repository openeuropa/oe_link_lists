<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_link_lists_test\SameConfigurationPluginTrait;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "same_configuration_source_two",
 *   label = @Translation("Same configuration source two."),
 *   description = @Translation("Same configuration source two."),
 *   bundles = { "dynamic" }
 * )
 */
class SameConfigurationSourceTwo extends LinkSourcePluginBase {

  use SameConfigurationPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    return new LinkCollection();
  }

}
