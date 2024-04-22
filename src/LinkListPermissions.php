<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_link_lists\Entity\LinkListType;

/**
 * Provides dynamic permissions for different link list types.
 */
class LinkListPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of link list permissions.
   */
  public function linkListTypePermissions() {
    $perms = [];
    // Generate link list permissions for all link list types.
    foreach (LinkListType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of link list permissions for a given link list type.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListType $type
   *   The link list type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(LinkListType $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id link list" => [
        'title' => $this->t('Create new %type_name link list', $type_params),
      ],
      "edit $type_id link list" => [
        'title' => $this->t('Edit any %type_name link list', $type_params),
      ],
      "delete $type_id link list" => [
        'title' => $this->t('Delete any %type_name link list', $type_params),
      ],
      "view any $type_id link list revisions" => [
        'title' => $this->t('%type_name: View any link list revision pages', $type_params),
      ],
      "revert any $type_id link list revisions" => [
        'title' => $this->t('Revert %type_name: Revert link list revisions', $type_params),
      ],
      "delete any $type_id link list revisions" => [
        'title' => $this->t('Delete %type_name: Delete link list revisions', $type_params),
      ],
    ];
  }

}
