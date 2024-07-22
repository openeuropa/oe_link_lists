<?php

declare(strict_types=1);

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
   * The no_results_behaviour plugin manager.
   *
   * @var \Drupal\oe_link_lists\NoResultsBehaviourPluginManagerInterface
   */
  protected $noResultsBehaviourPluginManager;

  /**
   * The more_link plugin manager.
   *
   * @var \Drupal\oe_link_lists\MoreLinkPluginManagerInterface
   */
  protected $moreLinkPluginManager;

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
   * @param \Drupal\oe_link_lists\NoResultsBehaviourPluginManagerInterface $no_results_behaviour_manager
   *   The no_results_behaviour plugin manager.
   * @param \Drupal\oe_link_lists\MoreLinkPluginManagerInterface $more_link_manager
   *   The more_link plugin manager.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(EntityTypeInterface $entity_type, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, Registry $theme_registry = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL, LinkSourcePluginManagerInterface $link_source_plugin_manager, LinkDisplayPluginManagerInterface $link_display_plugin_manager, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, NoResultsBehaviourPluginManagerInterface $no_results_behaviour_manager, MoreLinkPluginManagerInterface $more_link_manager) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->linkSourceManager = $link_source_plugin_manager;
    $this->linkDisplayManager = $link_display_plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->noResultsBehaviourPluginManager = $no_results_behaviour_manager;
    $this->moreLinkPluginManager = $more_link_manager;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.oe_link_lists.no_results_behaviour'),
      $container->get('plugin.manager.oe_link_lists.more_link')
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

        $this->moduleHandler()->invokeAll($view_hook, [
          &$build_list[$key],
          $entity,
          $display,
          $view_mode,
        ]);
        $this->moduleHandler()->invokeAll('entity_view', [
          &$build_list[$key],
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
    $cacheable_metadata = new CacheableMetadata();
    if (isset($configuration['more_link']) && isset($configuration['size']) && $configuration['size'] > 0) {
      $display_plugin_configuration['more'] = $this->prepareMoreLink($configuration['more_link'], $cacheable_metadata, $link_list);
    }

    foreach ($links as $key => $link) {
      /** @var \Drupal\oe_link_lists\LinkInterface $link */
      $access = $link->access('view', NULL, TRUE);
      $cacheable_metadata->addCacheableDependency($access);

      if (!$access->isAllowed()) {
        unset($links[$key]);
      }
    }

    // We need to run a check on the "no_results_behaviour" plugin to maintain
    // a level of BC.
    if ($links->isEmpty() && isset($configuration['no_results_behaviour']['plugin'])) {
      $no_results_behaviour_plugin = $configuration['no_results_behaviour']['plugin'];
      $no_results_behaviour_plugin_configuration = $configuration['no_results_behaviour']['plugin_configuration'] ?? [];
      /** @var \Drupal\oe_link_lists\NoResultsBehaviourInterface $plugin */
      $plugin = $this->noResultsBehaviourPluginManager->createInstance($no_results_behaviour_plugin, $no_results_behaviour_plugin_configuration);
      $build = $plugin->build($link_list);
      $cacheable_metadata->addCacheableDependency($links);
      $cacheable_metadata->applyTo($build);

      return $build;
    }

    // Pass the original link list id, so it can be used by the display plugin.
    $display_plugin_configuration['_link_list_id'] = $link_list->id();
    /** @var \Drupal\oe_link_lists\LinkDisplayInterface $plugin */
    $plugin = $this->linkDisplayManager->createInstance($display_plugin, $display_plugin_configuration);
    $build = $plugin->build($links);
    $cacheable_metadata->addCacheableDependency($links);
    $cacheable_metadata->applyTo($build);

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
    // Pass the original link list id, so it can be used by the source plugin.
    $source_plugin_configuration['_link_list_id'] = $link_list->id();
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
   * @param array $configuration
   *   The more_link configuration.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata.
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return \Drupal\Core\Link|null
   *   The Link object or NULL if one is not needed.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function prepareMoreLink(array $configuration, CacheableMetadata $cacheable_metadata, LinkListInterface $link_list): ?Link {
    if (!$configuration || !isset($configuration['plugin'])) {
      // It means no plugin has been configured for more_link.
      return NULL;
    }

    $more_link_plugin = $configuration['plugin'] ?? NULL;
    $more_link_plugin_configuration = $configuration['plugin_configuration'] ?? [];
    $plugin = $this->moreLinkPluginManager->createInstance($more_link_plugin, $more_link_plugin_configuration);
    return $plugin->getLink($link_list, $cacheable_metadata);

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

    // If the requested view mode is not set, the view builder will use either
    // the default view mode or a temporary view mode. In order to avoid
    // re-rendering, we assume non-existing view modes to be cacheable.
    if (!isset($view_modes_info[$view_mode])) {
      return TRUE;
    }

    return !empty($view_modes_info[$view_mode]['cache']);
  }

}
