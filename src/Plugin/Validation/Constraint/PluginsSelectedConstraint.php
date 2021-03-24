<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constrains a link list to have both plugin types configured.
 *
 * @Constraint(
 *   id = "PluginsSelected",
 *   label = @Translation("Plugins are selected", context = "Validation"),
 *   type = "string"
 * )
 */
class PluginsSelectedConstraint extends Constraint {

  /**
   * Message to show when there is no link source selected.
   *
   * @var string
   */
  public $noLinkSource = 'There is no link source selected';

  /**
   * Message to show when there is no link source selected.
   *
   * @var string
   */
  public $noLinkDisplay = 'There is no link display selected';

}
