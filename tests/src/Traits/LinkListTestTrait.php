<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;

/**
 * Provides helper methods for testing link lists.
 */
trait LinkListTestTrait {

  /**
   * Returns a link list entity given its title.
   *
   * @param string $title
   *   The link list title.
   * @param bool $reset
   *   Whether to reset the link list entity cache. Defaults to FALSE.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface|null
   *   The first link list entity that matches the title. NULL if not found.
   */
  protected function getLinkListByTitle(string $title, bool $reset = FALSE): ?LinkListInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('link_list');
    if ($reset) {
      $storage->resetCache();
    }

    $entities = $storage->loadByProperties(['title' => $title]);

    if (empty($entities)) {
      return NULL;
    }

    return reset($entities);
  }

  /**
   * Get Url to entity in given language.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $entity
   *   The entity.
   * @param string|null $langcode
   *   The language code.
   * @param string|null $rel
   *   The relation type.
   *
   * @return string|\Drupal\Core\Url
   *   The URL.
   */
  protected function getEntityUrl(ContentEntityInterface $entity, ?string $langcode = NULL, ?string $rel = 'canonical') {
    if (!$langcode) {
      return $entity->toUrl($rel)->toString();
    }

    // @todo Remove when support for 11.1.x is dropped.
    if (version_compare(\Drupal::VERSION, '11.2', '>=')) {
      return $langcode . '/' . $entity->toUrl($rel, ['path_processing' => FALSE, 'base_url' => ''])->toString();
    }
    else {
      return $entity->toUrl($rel, ['language' => \Drupal::languageManager()->getLanguage($langcode)]);
    }
  }

}
