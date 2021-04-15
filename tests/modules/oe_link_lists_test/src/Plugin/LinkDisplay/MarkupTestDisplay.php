<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Plugin implementation of the link_display.
 *
 * This plugin outputs to the screen all the link data so that it can be
 * asserted in tests.
 *
 * @LinkDisplay(
 *   id = "test_markup",
 *   label = @Translation("Markup"),
 *   description = @Translation("Displays the link values wrapped in markup."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class MarkupTestDisplay extends LinkDisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $items = [];
    foreach ($links as $link) {
      $items[] = [
        // We use an inline template so that in the test we can target the
        // values using the corresponding classes.
        '#type' => 'inline_template',
        '#template' => '<div class="link-list-test"><div class="link-list-test--title">{{ title }}</div><div class="link-list-test--teaser">{{ teaser }}</div><div class="link-list-test--url">{{ url }}</div></div>',
        '#context' => [
          'title' => $link->getTitle(),
          'teaser' => $link->getTeaser(),
          'url' => $link->getUrl()->toString(),
        ],
      ];
    };

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

}
