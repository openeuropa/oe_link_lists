<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_rss_source\Plugin\LinkSource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterFormatInterface;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Link source plugin that handles external RSS sources.
 *
 * It supports multiple RSS source URLs as input.
 *
 * @LinkSource(
 *   id = "rss_links",
 *   label = @Translation("RSS links"),
 *   description = @Translation("Source plugin that handles external RSS sources."),
 *   bundles = { "dynamic" }
 * )
 */
class RssLinksSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface, TranslatableLinkListPluginInterface {

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
  public function defaultConfiguration() {
    return [
      'urls' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['urls'] = array_column($form_state->getValue('urls'), 'url');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $defaults = [];
    foreach ($this->configuration['urls'] as $key => $url) {
      $defaults[$key] = ['url' => $url];
    }
    $form['urls'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('The RSS URLs'),
      '#description' => $this->t('Add the URLs where the external resources can be found.'),
      '#required' => TRUE,
      'url' => [
        '#type' => 'url',
        '#title' => $this->t('The resource URL'),
      ],
      '#default_value' => $defaults,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(ContentEntityInterface $entity): void {
    parent::preSave($entity);

    // Never allow empty values as URL.
    if (empty($this->configuration['urls'])) {
      return;
    }

    // Create feed for each of the configured URLs and refresh their items.
    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    foreach ($this->configuration['urls'] as $url) {
      if (!$this->hasFeed($url)) {
        /** @var \Drupal\aggregator\FeedInterface $feed */
        $feed = $feed_storage->create([
          'title' => $url,
          'url' => $url,
        ]);
        $feed->save();
        $feed->refreshItems();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $feeds = $this->getFeeds();
    $link_collection = new LinkCollection();

    if (empty($feeds)) {
      return $link_collection;
    }

    $feed_ids = [];
    foreach ($feeds as $feed) {
      $link_collection->addCacheableDependency($feed);
      // Make sure that we always use single aggregator feed per URL.
      $feed_ids[$feed->getUrl()] = $feed->id();
    }

    /** @var \Drupal\aggregator\ItemStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('aggregator_item');
    $query = $storage->getQuery()
      ->condition('fid', $feed_ids, 'IN')
      ->sort('timestamp', 'DESC')
      ->sort('iid', 'DESC');
    if ($limit) {
      $query->range($offset, $limit);
    }

    $ids = $query->accessCheck()->execute();
    if (!$ids) {
      return $link_collection;
    }

    return $this->prepareLinks($storage->loadMultiple($ids))->addCacheableDependency($link_collection);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      // The URL of the RSS source needs to be translatable. For this, we need
      // to mark both the top level "urls" field as translatable so that it
      // matches the form structure, as well as the inner "url" form element
      // so that it matches the plugin configuration structure when saving
      // translations.
      ['urls'],
      ['urls', 'url'],
    ];
  }

  /**
   * Returns a list of feed entities that match the plugin configuration.
   *
   * @return \Drupal\aggregator\FeedInterface[]
   *   A feed entity if a matching one is found, NULL otherwise.
   */
  protected function getFeeds(): array {
    if (empty($this->configuration['urls'])) {
      return [];
    }

    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    return $feed_storage->loadByProperties(['url' => $this->configuration['urls']]);
  }

  /**
   * Checks if we already have a feed for a given URL.
   *
   * @param string $url
   *   The URL.
   *
   * @return bool
   *   Whether a feed exists for the URL.
   */
  protected function hasFeed(string $url): bool {
    $feed_storage = $this->entityTypeManager->getStorage('aggregator_feed');
    return !empty($feed_storage->loadByProperties(['url' => $url]));
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
   * The method supports both 1.x and 2.x aggregator versions.
   *
   * @see _aggregator_allowed_tags()
   *
   * @return array
   *   The list of allowed tags.
   */
  protected function getAllowedTeaserTags(): array {
    $aggregator_format = FilterFormat::load('aggregator_html');
    if (!$aggregator_format instanceof FilterFormatInterface) {
      // If there is no such filter format, we can assume that the module
      // aggregator version is 1.x, so we get the allowed html from settings.
      $allowed_html = $this->configFactory->get('aggregator.settings')->get('items.allowed_html');
    }
    else {
      $config = $aggregator_format->filters('filter_html')->getConfiguration();
      $allowed_html = $config['settings']['allowed_html'];
    }
    return preg_split('/\s+|<|>/', $allowed_html, -1, PREG_SPLIT_NO_EMPTY);
  }

}
