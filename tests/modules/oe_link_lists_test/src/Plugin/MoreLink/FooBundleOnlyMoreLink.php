<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\MoreLink;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\MoreLinkPluginBase;

/**
 * Used only on the Foo bundle.
 *
 * @MoreLink(
 *   id = "foo_bundle_only",
 *   label = @Translation("Food bundle only"),
 *   description = @Translation("Food bundle only."),
 *   bundles = { "foo" }
 * )
 */
class FooBundleOnlyMoreLink extends MoreLinkPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLink(LinkListInterface $link_list, CacheableMetadata $cache): ?Link {
    return Link::fromTextAndUrl($this->t('A harcoded link'), Url::fromUri('http://europa.eu'));
  }

}
