<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines link_source annotation object.
 *
 * @Annotation
 */
class LinkSource extends Plugin {

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

  /**
   * Whether this plugin is meant for internal purposes.
   *
   * Internal plugins are not selectable by the user. By default, plugins are
   * not internal.
   *
   * @var bool
   */
  public $internal = FALSE;

  /**
   * The link list bundles this plugin should work with.
   *
   * This attribute is optional and if left empty, it means the plugin will be
   * considered for all bundles.
   *
   * @var array
   */
  public $bundles = [];

}
