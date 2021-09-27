<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;

/**
 * Interface for no_results_behaviour plugins.
 */
interface NoResultsBehaviourInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Builds a render array for a behaviour.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return array
   *   The render array.
   */
  public function build(LinkListInterface $link_list): array;

}
