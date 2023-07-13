<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a Link list link entity's requirements.
 *
 * The required fields of a Link list link entity depend on the contents of the
 * URL and Target fields.
 */
class LinkListLinkFieldsRequiredValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!isset($value) || !($value instanceof LinkListLinkInterface)) {
      return;
    }

    if ($value->bundle() === 'external') {
      $this->validateExternalLink($constraint, $value);
    }
  }

  /**
   * Helper function to validate the fields of external links.
   *
   * External links need to have a title and a teaser.
   *
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint for the validation.
   * @param \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link
   *   The entity being validated.
   */
  protected function validateExternalLink(Constraint $constraint, LinkListLinkInterface $link): void {
    $required_external_fields = [
      'title',
      'teaser',
    ];
    foreach ($required_external_fields as $field_name) {
      if ($link->get($field_name)->isEmpty()) {
        $this->context->buildViolation($constraint->message, ['@name' => $link->getFieldDefinition($field_name)->getLabel()])
          ->atPath($field_name)
          ->addViolation();
      }
    }
  }

}
