<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * A class that represents a collection of links.
 */
class LinkCollection implements LinkCollectionInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The link instances.
   *
   * @var \Drupal\oe_link_lists\LinkInterface[]
   */
  protected $links = [];

  /**
   * Instantiates a new LinkCollection object.
   *
   * @param \Drupal\oe_link_lists\LinkInterface[] $links
   *   A list of links.
   */
  public function __construct(array $links = []) {
    // Make use of the type declaration in the add() method.
    foreach ($links as $offset => $value) {
      $this->offsetSet($offset, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function add(LinkInterface $link): LinkCollectionInterface {
    $this->links[] = $link;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): void {
    $this->links = [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->links);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->links;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset): bool {
    return isset($this->links[$offset]) || array_key_exists($offset, $this->links);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset): mixed {
    return $this->links[$offset] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value): void {
    if (!$value instanceof LinkInterface) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid argument type: expected %s, got %s.',
        LinkInterface::class,
        is_object($value) ? get_class($value) : gettype($value)
      ));
    }

    // If the offset is not set, the [] operator has been used.
    if ($offset === NULL) {
      $this->add($value);
      return;
    }

    $this->links[$offset] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset): void {
    unset($this->links[$offset]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Iterator {
    return new \ArrayIterator($this->links);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = $this->cacheTags;

    foreach ($this->links as $link) {
      $tags = Cache::mergeTags($tags, $link->getCacheTags());
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = $this->cacheContexts;

    foreach ($this->links as $link) {
      $contexts = Cache::mergeContexts($contexts, $link->getCacheContexts());
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $max_age = $this->cacheMaxAge;

    foreach ($this->links as $link) {
      $max_age = Cache::mergeMaxAges($max_age, $link->getCacheMaxAge());
    }

    return $max_age;
  }

}
