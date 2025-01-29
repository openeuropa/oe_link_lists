<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Default link implementation for LinkSource links that have entities.
 *
 * This is used when rendering of the link needs to take into account more
 * data that just the basic things covered by LinkInterface.
 */
class DefaultEntityLink extends DefaultLink implements EntityAwareLinkInterface {

  /**
   * The content entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(ContentEntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $entity_tags = $this->entity ? $this->entity->getCacheTags() : [];
    return Cache::mergeTags($this->cacheTags, $entity_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $entity_contexts = $this->entity ? $this->entity->getCacheContexts() : [];
    return Cache::mergeContexts($this->cacheContexts, $entity_contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $max_age = $this->entity ? $this->entity->getCacheMaxAge() : Cache::PERMANENT;
    return Cache::mergeMaxAges($this->cacheMaxAge, $max_age);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($operation, $account, TRUE);

    if ($this->entity) {
      $result = $result->andIf($this->entity->access('view', $account, TRUE));
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
