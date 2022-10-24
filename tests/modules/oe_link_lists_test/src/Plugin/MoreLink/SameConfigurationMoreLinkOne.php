<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\MoreLink;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\MoreLinkPluginBase;
use Drupal\oe_link_lists_test\SameConfigurationPluginTrait;

/**
 * Plugin implementation of the more_link.
 *
 * @MoreLink(
 *   id = "same_configuration_more_link_one",
 *   label = @Translation("Same configuration more_link one."),
 *   description = @Translation("Same configuration more_link one."),
 *   bundles = { "dynamic" }
 * )
 */
class SameConfigurationMoreLinkOne extends MoreLinkPluginBase {

  use SameConfigurationPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function getLink(LinkListInterface $link_list, CacheableMetadata $cache): ?Link {
    return NULL;
  }

}
