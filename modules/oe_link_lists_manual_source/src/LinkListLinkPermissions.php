<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkType;

/**
 * Provides dynamic permissions for different link list types.
 */
class LinkListLinkPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of link list link permissions.
   *
   * @return array
   *   The link list link permissions.
   */
  public function linkListLinkPermissions() {
    $perms = [];
    // Generate link list permissions for all link list types.
    foreach (LinkListLinkType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of link list link permissions for a given link list link
   * type.
   *
   * @param Drupal\oe_link_lists_manual_source\Entity\LinkListLink $type
   *   The link list link type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(LinkListLinkType $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id link list link" => [
        'title' => $this->t('Create new %type_name link list link', $type_params),
      ],
      "edit own $type_id link list link" => [
        'title' => $this->t('Edit own %type_name link list link', $type_params),
      ],
      "edit any $type_id link list link" => [
        'title' => $this->t('Edit any %type_name link list link', $type_params),
      ],
      "delete own $type_id link list link" => [
        'title' => $this->t('Delete own %type_name link list link', $type_params),
      ],
      "delete any $type_id link list link" => [
        'title' => $this->t('Delete any %type_name link list link', $type_params),
      ],
    ];
  }
}