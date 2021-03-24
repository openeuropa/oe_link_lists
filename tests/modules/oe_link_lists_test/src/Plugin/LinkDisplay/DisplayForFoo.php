<?php

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "display_for_foo",
 *   label = @Translation("Display for Foo"),
 *   description = @Translation("Display plugin only available for the Foo link source."),
 *   bundles = { "dynamic" },
 *   link_sources = { "foo" }
 * )
 */
class DisplayForFoo extends LinkDisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $items = [];
    foreach ($links as $link) {
      $items[] = Link::fromTextAndUrl($link->getTitle(), $link->getUrl());
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

}
