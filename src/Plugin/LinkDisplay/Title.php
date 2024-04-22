<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\LinkDisplay;

use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Title display of link list links.
 *
 * Renders a simple list of links.
 *
 * @LinkDisplay(
 *   id = "title",
 *   label = @Translation("Title"),
 *   description = @Translation("Simple title link list."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class Title extends LinkDisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $items = [];
    foreach ($links as $link) {
      $items[] = Link::fromTextAndUrl($link->getTitle(), $link->getUrl());
    }

    $build = parent::build($links);

    $build['list'] = [
      '#theme' => 'item_list__title_link_display_plugin',
      '#items' => $items,
      '#title' => $this->configuration['title'],
    ];

    return $build;
  }

}
