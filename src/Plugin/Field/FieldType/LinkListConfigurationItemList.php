<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;

/**
 * Field item list class for the link list configuration field type.
 */
class LinkListConfigurationItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $value = parent::get($index);
    if ($index !== 0) {
      // If a non 0 delta was requested, we return whatever the parent did.
      return $value;
    }

    if (is_null($value)) {
      // Create an empty item if one is not there already.
      $this->list[0] = $this->createItem(0);
      return $this->list[$index] ?? NULL;
    }

    return $value;
  }

}
