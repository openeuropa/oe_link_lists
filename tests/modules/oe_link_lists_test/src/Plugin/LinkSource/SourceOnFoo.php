<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\Plugin\ExternalLinkSourcePluginBase;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "source_on_foo",
 *   label = @Translation("Source on Foo"),
 *   description = @Translation("Link source available only on Foo bundle"),
 *   bundles = { "foo" }
 * )
 */
class SourceOnFoo extends ExternalLinkSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    return new LinkCollection();
  }

}
