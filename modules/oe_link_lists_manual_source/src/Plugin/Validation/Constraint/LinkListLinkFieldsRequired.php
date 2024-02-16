<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if the Link list link fields are provided if required.
 *
 * @Constraint(
 *   id = "LinkListLinkFieldsRequired",
 *   label = @Translation("Link list link fields required", context = "Validation")
 * )
 */
class LinkListLinkFieldsRequired extends Constraint {

  /**
   * Violation message. Use the same message as FormValidator.
   *
   * Note that the name argument is not sanitized so that translators only have
   * one string to translate. The name is sanitized in self::validate().
   *
   * @var string
   */
  public $message = '@name field is required.';

}
