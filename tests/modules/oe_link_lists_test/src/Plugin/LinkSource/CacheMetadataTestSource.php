<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\Plugin\ExternalLinkSourcePluginBase;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "test_cache_metadata",
 *   label = @Translation("Cache metadata"),
 *   description = @Translation("Source that returns extra cache metadata information."),
 *   bundles = { "dynamic" }
 * )
 */
class CacheMetadataTestSource extends ExternalLinkSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $collection = new LinkCollection([
      (new DefaultLink(Url::fromUri('http://example.com'), 'Example', ['#markup' => 'Example teaser']))->addCacheTags(['bar_test_tag:1']),
      (new DefaultLink(Url::fromUri('http://ec.europa.eu'), 'European Commission', ['#markup' => 'European teaser']))->addCacheTags(['bar_test_tag:2']),
    ]);

    $collection
      // Cache contexts are validated so we need to use an existing one.
      ->addCacheContexts(['user.is_super_user'])
      ->addCacheTags(['test_cache_metadata_tag'])
      ->mergeCacheMaxAge(1800);

    return $collection;
  }

}
