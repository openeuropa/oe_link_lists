<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines no_results_behaviour annotation object.
 *
 * @Annotation
 */
class NoResultsBehaviour extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
