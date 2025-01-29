<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\Plugin\ExternalLinkSourcePluginBase;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "test_empty_collection_with_cache",
 *   label = @Translation("Empty collection with cache"),
 *   description = @Translation("A source that will return an empty collection but with cache metadata."),
 *   bundles = { "dynamic" }
 * )
 */
class EmptyCollectionWithCacheTestSource extends ExternalLinkSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLinks(?int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $collection = new LinkCollection();
    $collection
      ->addCacheContexts(['user.permissions'])
      ->addCacheTags(['test_cache_metadata_tag']);
    return $collection;
  }

}
