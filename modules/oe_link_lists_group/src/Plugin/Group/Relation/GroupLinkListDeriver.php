<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_group\Plugin\Group\Relation;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeInterface;
use Drupal\oe_link_lists\Entity\LinkListType;

/**
 * Derives plugins based on link list type.
 */
class GroupLinkListDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    assert($base_plugin_definition instanceof GroupRelationTypeInterface);
    $this->derivatives = [];

    foreach (LinkListType::loadMultiple() as $name => $link_list_type) {
      $label = $link_list_type->label();

      $this->derivatives[$name] = clone $base_plugin_definition;
      $this->derivatives[$name]->set('entity_bundle', $name);
      $this->derivatives[$name]->set('label', t('Group link list (@type)', ['@type' => $label]));
      $this->derivatives[$name]->set('description', t('Adds %type link list to groups both publicly and privately.', ['%type' => $label]));
    }

    return $this->derivatives;
  }

}
