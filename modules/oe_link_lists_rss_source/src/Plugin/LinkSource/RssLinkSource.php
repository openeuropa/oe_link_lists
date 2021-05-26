<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_rss_source\Plugin\LinkSource;

use Drupal\aggregator\FeedInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\Plugin\ExternalLinkSourcePluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Link source plugin that handles external RSS sources.
 *
 * @LinkSource(
 *   id = "rss",
 *   label = @Translation("RSS"),
 *   description = @Translation("Source plugin that handles external RSS sources."),
 *   bundles = { "dynamic" }
 * )
 */
class RssLinkSource extends ExternalLinkSourcePluginBase implements ContainerFactoryPluginInterface, TranslatableLinkListPluginInterface {

  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a RssLinkSource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(ContentEntityInterface $entity): void {
    parent::preSave($entity);

    // Never allow empty values as URL.
    if (empty($this->configuration['url'])) {
      return;
    }

    // Check if a feed entity already exists for the provided URL.
    if (!empty($this->getFeed())) {
      return;
    }

    // Create a new feed and run an initial import of its items.
    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    /** @var \Drupal\aggregator\FeedInterface $feed */
    $feed = $feed_storage->create([
      'title' => $this->configuration['url'],
      'url' => $this->configuration['url'],
    ]);
    $feed->save();
    $feed->refreshItems();
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $feed = $this->getFeed();
    $link_collection = new LinkCollection();

    if (empty($feed)) {
      return $link_collection;
    }

    $link_collection->addCacheableDependency($feed);

    /** @var \Drupal\aggregator\ItemStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('aggregator_item');
    $query = $storage->getQuery()
      ->condition('fid', $feed->id())
      ->sort('timestamp', 'DESC')
      ->sort('iid', 'DESC');
    if ($limit) {
      $query->range($offset, $limit);
    }

    $ids = $query->execute();
    if (!$ids) {
      return $link_collection;
    }

    return $this->prepareLinks($storage->loadMultiple($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      // The URL of the RSS source needs to be translatable.
      ['url'],
    ];
  }

  /**
   * Returns a feed entity that matches the current plugin configuration.
   *
   * @return \Drupal\aggregator\FeedInterface|null
   *   A feed entity if a matching one is found, NULL otherwise.
   */
  protected function getFeed(): ?FeedInterface {
    if (empty($this->configuration['url'])) {
      return NULL;
    }

    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    $feeds = $feed_storage->loadByProperties(['url' => $this->configuration['url']]);

    if (empty($feeds)) {
      return NULL;
    }

    return reset($feeds);
  }

  /**
   * Prepares the links from the aggregator items.
   *
   * @param \Drupal\aggregator\ItemInterface[] $entities
   *   Aggregator items.
   *
   * @return \Drupal\oe_link_lists\LinkCollectionInterface
   *   The link objects.
   */
  protected function prepareLinks(array $entities): LinkCollectionInterface {
    $links = new LinkCollection();
    foreach ($entities as $entity) {
      $teaser = [
        '#markup' => $entity->getDescription(),
        '#allowed_tags' => $this->getAllowedTeaserTags(),
      ];
      try {
        $url = Url::fromUri($entity->getLink());
      }
      catch (\InvalidArgumentException $exception) {
        $url = Url::fromRoute('<front>');
      }
      $link = new DefaultEntityLink($url, strip_tags($entity->getTitle()), $teaser);
      $link->setEntity($entity);
      $links[] = $link;
    }

    return $links;
  }

  /**
   * Prepares the allowed tags when stripping the teaser.
   *
   * These tags are configured as part of the Aggregator module and the method
   * is heavily inspired from there.
   *
   * @see _aggregator_allowed_tags()
   *
   * @return array
   *   The list of allowed tags.
   */
  protected function getAllowedTeaserTags(): array {
    return preg_split('/\s+|<|>/', $this->configFactory->get('aggregator.settings')->get('items.allowed_html'), -1, PREG_SPLIT_NO_EMPTY);
  }

}
