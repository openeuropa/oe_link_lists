<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source\Plugin\LinkSource;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceBundlesEvent;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceEntityTypesEvent;
use Drupal\oe_link_lists_internal_source\Event\InternalSourceQueryEvent;
use Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Link source plugin that links to internal entities.
 *
 * @LinkSource(
 *   id = "internal",
 *   label = @Translation("Internal"),
 *   description = @Translation("Source plugin that links to internal entities."),
 *   bundles = { "dynamic" }
 * )
 */
class InternalLinkSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The internal link source filter plugin manager.
   *
   * @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginManagerInterface
   */
  protected $filterPluginManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs an InternalLinkSource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle info service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginManagerInterface $filter_plugin_manager
   *   The internal link source filter plugin manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EventDispatcherInterface $event_dispatcher, InternalLinkSourceFilterPluginManagerInterface $filter_plugin_manager, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $event_dispatcher;
    $this->filterPluginManager = $filter_plugin_manager;
    $this->entityRepository = $entity_repository;
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
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.oe_link_lists.internal_source_filter'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_type' => '',
      'bundle' => '',
      'filters' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $input = $form_state->getUserInput();
    $entity_type = NestedArray::getValue($input, array_merge($form['#parents'], ['entity_type'])) ?? $this->configuration['entity_type'];

    $form['#type'] = 'container';
    $form['#id'] = $form['#id'] ?? Html::getUniqueId('internal-link-source');

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#required' => TRUE,
      '#options' => $this->getReferenceableEntityTypes(),
      '#default_value' => $entity_type,
      '#empty_value' => '',
      '#ajax' => [
        'callback' => [$this, 'updateBundleSelect'],
        'wrapper' => $form['#id'],
      ],
    ];

    if (!$entity_type) {
      return $form;
    }

    $available_bundles = $this->getReferenceableEntityBundles($entity_type);
    $bundle = NestedArray::getValue($input, array_merge($form['#parents'], ['bundle'])) ?? $this->configuration['bundle'] ?? $this->configuration['bundle'];

    // If only one bundle is present with the same name of the entity type,
    // hide the choice and force the value.
    if (count($available_bundles) === 1 && isset($available_bundles[$entity_type])) {
      $form['bundle'] = [
        '#type' => 'value',
        '#value' => $entity_type,
      ];
    }
    else {
      $form['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#required' => TRUE,
        '#options' => $available_bundles,
        '#default_value' => $bundle,
        '#empty_value' => '',
        '#ajax' => [
          'callback' => [$this, 'updateFilterPlugins'],
          'wrapper' => $form['#id'],
        ],
      ];
    }

    $form['filters'] = [
      '#process' => [[$this, 'expandFilterPlugins']],
    ];

    return $form;
  }

  /**
   * Process callback to include the filter plugins in the form.
   *
   * @param array $element
   *   The element form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The element form.
   */
  public function expandFilterPlugins(array &$element, FormStateInterface $form_state, array $complete_form): array {
    $plugin_form = NestedArray::getValue($complete_form, array_slice($element['#array_parents'], 0, -1));
    $subform_state = SubformState::createForSubform($plugin_form, $complete_form, $form_state);

    $entity_type = $subform_state->getValue('entity_type');
    $bundle = $subform_state->getValue('bundle');

    // Include the filter plugins only when both values have been specified.
    if (empty($entity_type) || empty($bundle)) {
      return $element;
    }

    foreach ($this->filterPluginManager->getApplicablePlugins($entity_type, $bundle) as $plugin_id => $plugin) {
      /** @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface $plugin */
      $plugin->setConfiguration($this->configuration['filters'][$plugin_id] ?? []);

      $element[$plugin_id] = [];
      $plugin_form_state = SubformState::createForSubform($element[$plugin_id], $complete_form, $form_state);
      $element[$plugin_id] = $plugin->buildConfigurationForm($element[$plugin_id], $plugin_form_state);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['entity_type'] = $form_state->getValue('entity_type');
    $this->configuration['bundle'] = $form_state->getValue('bundle');

    // Retrieve all the filter plugins and add their configuration.
    foreach (array_keys($form_state->getValue('filters', [])) as $plugin_id) {
      /** @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface $plugin */
      $plugin = $this->filterPluginManager->createInstance($plugin_id);
      $plugin_form_state = SubformState::createForSubform($form['filters'][$plugin_id], $form, $form_state);
      $plugin->submitConfigurationForm($form['filters'][$plugin_id], $plugin_form_state);
      $this->configuration['filters'][$plugin_id] = $plugin->getConfiguration();
    }
  }

  /**
   * Ajax callback to update the bundle select form element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The updated form element.
   */
  public function updateBundleSelect(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    // Reset the value for the bundle field and filter plugins.
    $form_state->setValue(array_merge($element['#parents'], ['bundle']), NULL);
    $form_state->setValue(array_merge($element['#parents'], ['filters']), NULL);

    return $element;
  }

  /**
   * Ajax callback to update the filter plugins form elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The updated form element.
   */
  public function updateFilterPlugins(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    // Reset the value for filter plugins.
    $form_state->setValue(array_merge($element['#parents'], ['filters']), NULL);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $entity_type_id = $this->configuration['entity_type'];
    $bundle_id = $this->configuration['bundle'];
    $links = new LinkCollection();

    // Bail out if the configuration is not provided.
    if (empty($entity_type_id) || empty($bundle_id)) {
      return $links;
    }

    try {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    }
    catch (PluginNotFoundException $exception) {
      // The entity is not available anymore in the system.
      return $links;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery();

    if ($entity_type->hasKey('bundle')) {
      $query->condition($entity_type->getKey('bundle'), $bundle_id);
    }
    if ($limit !== NULL) {
      $query->range($offset, $limit);
    }

    // Run all the enabled filter plugins.
    $context = [
      'entity_type' => $entity_type_id,
      'bundle' => $bundle_id,
    ];
    foreach ($this->configuration['filters'] as $plugin_id => $configuration) {
      /** @var \Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterInterface $plugin */
      $plugin = $this->filterPluginManager->createInstance($plugin_id, $configuration);
      $cacheability = new CacheableMetadata();
      $plugin->apply($query, $context, $cacheability);

      // Apply the cacheability information provided by the plugin to the link
      // collection.
      $links->addCacheableDependency($cacheability);
    }

    // Allow others to alter the query to apply things like sorting, etc.
    $query->addMetaData('oe_link_lists_internal_source', $this->configuration);
    $event = new InternalSourceQueryEvent($query);
    $this->eventDispatcher->dispatch($event, InternalSourceQueryEvent::NAME);

    $entities = $storage->loadMultiple($query->accessCheck(TRUE)->execute());
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $event = new EntityValueResolverEvent($entity);
      $this->eventDispatcher->dispatch($event, EntityValueResolverEvent::NAME);
      $links[] = $event->getLink();
    }

    $links->addCacheContexts($entity_type->getListCacheContexts());
    $links->addCacheTags($entity_type->getListCacheTags());

    return $links;
  }

  /**
   * Returns a list of entity types that can be referenced by the plugin.
   *
   * @return array
   *   A list of entity type labels, keyed by entity type ID.
   */
  protected function getReferenceableEntityTypes(): array {
    $entity_types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      // Remove bundleable entities that have no bundles declared.
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if (empty($bundle_info)) {
        continue;
      }

      $entity_types[$entity_type_id] = $entity_type->getLabel();
    }

    $event = new InternalSourceEntityTypesEvent(array_keys($entity_types));
    $this->eventDispatcher->dispatch($event, InternalSourceEntityTypesEvent::NAME);
    $entity_types = array_intersect_key($entity_types, array_flip($event->getEntityTypes()));

    return $entity_types;
  }

  /**
   * Returns all the bundles of a certain entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   A list of bundle labels, keyed by bundle ID.
   */
  protected function getReferenceableEntityBundles(string $entity_type_id): array {
    $bundles = [];

    foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $info) {
      $bundles[$bundle_id] = $info['label'];
    }

    $event = new InternalSourceBundlesEvent($entity_type_id, array_keys($bundles));
    $this->eventDispatcher->dispatch($event, InternalSourceBundlesEvent::NAME);
    $bundles = array_intersect_key($bundles, array_flip($event->getBundles()));

    return $bundles;
  }

}
