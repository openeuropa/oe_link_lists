<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\NoResultsBehaviour;

use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\NoResultsBehaviourPluginBase;
use Drupal\oe_link_lists_test\SameConfigurationPluginTrait;

/**
 * Plugin implementation of the no_results.
 *
 * @NoResultsBehaviour(
 *   id = "same_configuration_no_results_two",
 *   label = @Translation("Same configuration no_results two."),
 *   description = @Translation("Same configuration no_results two."),
 *   bundles = { "dynamic" }
 * )
 */
class SameConfigurationNoResultsTwo extends NoResultsBehaviourPluginBase {

  use SameConfigurationPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function build(LinkListInterface $link_list): array {
    return [];
  }

}
