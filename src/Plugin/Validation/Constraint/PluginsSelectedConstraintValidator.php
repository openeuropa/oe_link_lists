<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PluginsSelectedConstraint.
 */
class PluginsSelectedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\oe_link_lists\Plugin\Field\FieldType\LinkListConfigurationItem $item */
    $item = $value;
    if (!$item->getEntity()->isDefaultTranslation()) {
      // When we translate link lists, the configuration values are smaller as
      // they contain only the translatable values. So we don't need to validate
      // here as the original translation was validated already.
      return;
    }

    $value = $item->getValue();
    if (!isset($value['source']) || !$value['source'] || !$value['source']['plugin']) {
      $this->context->addViolation($constraint->noLinkSource);
    }

    if (!isset($value['display']) || !$value['display'] || !$value['display']['plugin']) {
      $this->context->addViolation($constraint->noLinkDisplay);
    }
  }

}
