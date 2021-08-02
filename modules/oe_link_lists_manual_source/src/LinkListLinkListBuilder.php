<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Link list link entities.
 *
 * @ingroup oe_link_lists
 */
class LinkListLinkListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('Link list link ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\oe_link_lists\Entity\LinkListLink $entity */
    $row['id'] = $entity->id();

    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.link_list_link.edit_form',
      ['link_list_link' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
