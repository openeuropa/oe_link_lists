<?php

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "display_on_foo",
 *   label = @Translation("Display on foo"),
 *   description = @Translation("Display plugin only available on the Foo bundle."),
 *   bundles = { "foo" }
 * )
 */
class DisplayOnFoo extends LinkDisplayPluginBase {

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
