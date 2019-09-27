<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Link list type entities.
 *
 * @ingroup oe_link_lists
 */
class LinkListTypeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    $header['id'] = $this->t('Machine name');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['description'] = $entity->getDescription();
    $row['id'] = $entity->id();

    return $row + parent::buildRow($entity);
  }

}