<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * A generic base class for test display plugins.
 */
class GenericTestDisplayBase extends LinkDisplayPluginBase {

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
