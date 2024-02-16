<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\NoResultsBehaviour;

use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\NoResultsBehaviourPluginBase;

/**
 * Hides the link list when there are no results.
 *
 * This is the default behaviour when there are no results.
 *
 * @NoResultsBehaviour(
 *   id = "hide_list",
 *   label = @Translation("Hide"),
 *   description = @Translation("Simply hide the link list.")
 * )
 */
class HideList extends NoResultsBehaviourPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(LinkListInterface $link_list): array {
    // There is nothing we need to do for this case but return an empty
    // array. The cache metadata is added by the link list builder.
    return [];
  }

}
