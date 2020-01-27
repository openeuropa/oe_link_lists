<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * View builder for the LinkList entities.
 */
class LinkListViewBuilder extends EntityViewBuilder {

  /**
   * The link source plugin manager.
   *
   * @var \Drupal\oe_link_lists\LinkSourcePluginManagerInterface
   */
  protected $linkSourceManager;

  /**
   * The link display plugin manager.
   *
   * @var \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface
   */
  protected $linkDisplayManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LinkListViewBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\oe_link_lists\LinkSourcePluginManagerInterface $link_source_plugin_manager
   *   The link source plugin manager.
   * @param \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface $link_display_plugin_manager
   *   The link display plugin manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, Registry $theme_registry = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL, LinkSourcePluginManagerInterface $link_source_plugin_manager, LinkDisplayPluginManagerInterface $link_display_plugin_manager, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->linkSourceManager = $link_source_plugin_manager;
    $this->linkDisplayManager = $link_display_plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('plugin.manager.oe_link_lists.link_source'),
      $container->get('plugin.manager.oe_link_lists.link_display'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Heavily inspired from the parent class. The main difference is that we
   * are not building the components but deferring the the configured display
   * plugins to build the render of the list. We do keep the ability for the
   * build to be altered by other modules.
   */
  public function buildMultiple(array $build_list) {
    // Build the view modes and display objects.
    $view_modes = [];
    $entity_type_key = "#{$this->entityTypeId}";
    $view_hook = "{$this->entityTypeId}_view";

    // Find the keys for the ContentEntities in the build; Store entities for
    // rendering by view_mode.
    $children = Element::children($build_list);
    foreach ($children as $key) {
      if (isset($build_list[$key][$entity_type_key])) {
        $entity = $build_list[$key][$entity_type_key];
        if ($entity instanceof FieldableEntityInterface) {
          $view_modes[$build_list[$key]['#view_mode']][$key] = $entity;
        }
      }
    }

    // Build content for the displays represented by the entities.
    foreach ($view_modes as $view_mode => $view_mode_entities) {
      $displays = EntityViewDisplay::collectRenderDisplays($view_mode_entities, $view_mode);
      foreach (array_keys($view_mode_entities) as $key) {
        // Allow for alterations while building, before rendering.
        $entity = $build_list[$key][$entity_type_key];
        $build_list[$key]['entity'] = $this->buildEntity($entity);

        $display = $displays[$entity->bundle()];

        $this->moduleHandler()->invokeAll(
          $view_hook,
          [&$build_list[$key],
            $entity,
            $display,
            $view_mode,
          ]);
        $this->moduleHandler()->invokeAll(
          'entity_view',
          [&$build_list[$key],
            $entity,
            $display,
            $view_mode,
          ]);

        $this->addContextualLinks($build_list[$key], $entity);
        $this->alterBuild($build_list[$key], $entity, $display, $view_mode);

        // Allow modules to modify the render array.
        $this->moduleHandler()->alter([$view_hook, 'entity_view'], $build_list[$key], $entity, $display);
      }
    }

    return $build_list;
  }

  /**
   * Builds the display of a single link list.
   *
   * It uses the source plugin to retrieve the links and defers to the
   * display plugin to handle the rendering.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return array
   *   The built link list.
   */
  protected function buildEntity(LinkListInterface $link_list): array {
    $links = $this->getLinksFromList($link_list);
    $configuration = $link_list->getConfiguration();

    $display_plugin = $configuration['display']['plugin'];
    $display_plugin_configuration = $configuration['display']['plugin_configuration'] ?? [];
    if ($link_list->getTitle()) {
      $display_plugin_configuration['title'] = $link_list->getTitle();
    }
    if (isset($configuration['more']) && isset($configuration['size']) && $configuration['size'] > 0) {
      $display_plugin_configuration['more'] = $this->prepareMoreLink($configuration['more']);
    }

    $access_cacheability = new CacheableMetadata();
    foreach ($links as $key => $link) {
      /** @var \Drupal\oe_link_lists\LinkInterface $link */
      $access = $link->access('view', NULL, TRUE);
      $access_cacheability->addCacheableDependency($access);

      if (!$access->isAllowed()) {
        unset($links[$key]);
      }
    }

    /** @var \Drupal\oe_link_lists\LinkDisplayInterface $plugin */
    $plugin = $this->linkDisplayManager->createInstance($display_plugin, $display_plugin_configuration);
    $build = $plugin->build($links);

    // Apply the cacheability information of the link collection to the render
    // array.
    CacheableMetadata::createFromObject($links)
      ->merge($access_cacheability)
      ->applyTo($build);

    return $build;
  }

  /**
   * Returns the links of a given list.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return \Drupal\oe_link_lists\LinkCollectionInterface
   *   The link objects.
   */
  protected function getLinksFromList(LinkListInterface $link_list): LinkCollectionInterface {
    $configuration = $link_list->getConfiguration();
    $source_plugin = $configuration['source']['plugin'] ?? NULL;
    $source_plugin_configuration = $configuration['source']['plugin_configuration'] ?? [];

    // For lists that use source plugins.
    if ($source_plugin) {
      $plugin = $this->linkSourceManager->createInstance($source_plugin, $source_plugin_configuration);
      $size = isset($configuration['size']) && $configuration['size'] > 0 ? $configuration['size'] : NULL;
      return $plugin->getLinks($size);
    }

    return new LinkCollection();
  }

  /**
   * Prepares the "See all" link for the list.
   *
   * @param array $more
   *   The link configuration.
   *
   * @return \Drupal\Core\Link|null
   *   The Link object or NULL if one is not needed.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function prepareMoreLink(array $more): ?Link {
    if ($more['button'] === 'no') {
      return NULL;
    }

    $overridden_title = FALSE;
    $title = $this->t('See all');
    if (isset($more['title_override']) && mb_strlen($more['title_override']) > 0) {
      $overridden_title = TRUE;
      $title = $more['title_override'];
    }

    if ($more['button'] === 'custom' && $more['target']['type'] === 'custom') {
      $has_scheme = parse_url($more['target']['url'], PHP_URL_SCHEME) !== NULL;
      try {
        $url = $has_scheme ? Url::fromUri($more['target']['url']) : Url::fromUserInput($more['target']['url']);
      }
      catch (\InvalidArgumentException $exception) {
        if ($more['target']['url'] !== '<front>') {
          return NULL;
        }

        $url = Url::fromRoute('<front>');
      }

      return Link::fromTextAndUrl($title, $url);
    }

    if ($more['button'] === 'custom' && $more['target']['type'] === 'entity') {
      $url = Url::fromUri("entity:{$more['target']['entity_type']}/{$more['target']['entity_id']}");
      if (!$overridden_title) {
        $entity = $this->entityTypeManager->getStorage($more['target']['entity_type'])->load($more['target']['entity_id']);
        $title = $entity->label();
      }
      return Link::fromTextAndUrl($title, $url);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function isViewModeCacheable($view_mode) {
    if ($view_mode === 'default') {
      // The 'default' is not an actual view mode.
      return TRUE;
    }

    $view_modes_info = $this->entityDisplayRepository->getViewModes($this->entityTypeId);

    // If the requested view mode is not set, the view builder will use the
    // default view mode.
    if (!isset($view_modes_info[$view_mode])) {
      return TRUE;
    }

    return !empty($view_modes_info[$view_mode]['cache']);
  }

}
