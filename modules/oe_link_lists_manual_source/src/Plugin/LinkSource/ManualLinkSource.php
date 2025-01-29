<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_manual_source\Plugin\LinkSource;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent;
use Drupal\oe_link_lists_manual_source\Event\ManualLinksResolverEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Link source plugin that allows to enter links manually.
 *
 * @LinkSource(
 *   id = "manual_links",
 *   label = @Translation("Manual links"),
 *   description = @Translation("Source plugin that handles manual links."),
 *   internal = TRUE
 * )
 */
class ManualLinkSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface, TranslatableLinkListPluginInterface {

  use DependencySerializationTrait;

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
   * Constructs a ManualLinkSource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'links' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // We do nothing here because due to the complexity of the inline entity
    // form embed we have to handle it in a form alter.
    // @see oe_link_lists_manual_source_link_list_form_handle_alter()
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here as we copy the referenced link IDs to the plugin
    // configuration inside preSave().
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(ContentEntityInterface $entity): void {
    parent::preSave($entity);

    if ($entity->get('links')->isEmpty()) {
      // If there are no referenced links we don't have to do anything.
      return;
    }

    // Update each referenced link list link with the parent (link list).
    $ids = $this->getLinkIds($entity);
    foreach ($ids as $id_info) {
      // @todo move this to IEF directly where the entity is being built.
      /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
      $link = $this->entityTypeManager->getStorage('link_list_link')->load($id_info['entity_id']);
      if ($link->get('parent_id')->isEmpty()) {
        // Only set the parent entity if it's not already set.
        $link->setParentEntity($entity, 'links');
        $link->setNewRevision(FALSE);
        $link->save();
      }
    }

    // Set the referenced link list links onto the plugin configuration. We
    // need to do this for all languages in case the link list is getting saved
    // together with multiple languages (as opposed to a translation-specific
    // save).
    foreach ($entity->getTranslationLanguages(TRUE) as $language) {
      $translation = $entity->getTranslation($language->getId());
      $ids = $this->getLinkIds($translation);
      $configuration = $translation->getConfiguration();
      $configuration['source']['plugin_configuration']['links'] = $ids;
      $translation->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(?int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $ids = $this->configuration['links'];
    if (!$ids) {
      return new LinkCollection();
    }

    $ids = array_slice($ids, $offset, $limit);
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface[] $link_entities */
    $link_entities = $this->entityTypeManager->getStorage('link_list_link')->loadMultipleRevisions(array_column($ids, 'entity_revision_id'));

    // For legacy reasons, we need to first dispatch the event responsible for
    // resolving all links if there are any subscribers to this event.
    // @phpstan-ignore-next-line
    $listeners = $this->eventDispatcher->getListeners(ManualLinksResolverEvent::NAME);
    if ($listeners) {
      return $this->legacyResolveLinks($link_entities);
    }

    // Otherwise we resolve the links by dispatching an event for each of them.
    $links = new LinkCollection();
    foreach ($link_entities as $link_entity) {
      $event = new ManualLinkResolverEvent($link_entity);
      $this->eventDispatcher->dispatch($event, ManualLinkResolverEvent::NAME);
      if ($event->hasLink()) {
        $link = $event->getLink();
        $link->addCacheableDependency($link_entity);
        $links->add($link);
      }
    }

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      [
        // We want to translate the links key because we store entity IDs
        // and revisions so when we translate links, new revisions are created
        // so the list translations need to keep track of these.
        'links',
      ],
    ];
  }

  /**
   * Returns the link list link IDs referenced on a link list (translation).
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return array
   *   The array of IDs and revision IDs, keyed by the revision ID.
   */
  protected function getLinkIds(LinkListInterface $link_list): array {
    $ids = [];
    foreach ($link_list->get('links')->getValue() as $value) {
      $link_entity_reference = $value['entity'] ?? NULL;
      $id = $link_entity_reference ? $link_entity_reference->id() : $value['target_id'];
      $revision_id = $link_entity_reference ? $link_entity_reference->getRevisionId() : $value['target_revision_id'];
      if (empty($revision_id)) {
        // As this method is called by the preSave() method of the LinkList
        // entity, the revision id will not always be available because
        // the entity_reference_revision module will not have updated
        // the LinkListLink yet. When this happens, we need to reload
        // the LinkListEntity to get the latest revision id.
        $link_list_link = $this->entityTypeManager->getStorage('link_list_link')->load($id);
        if ($link_list_link) {
          $revision_id = $link_list_link->getRevisionId();
        }
      }
      $ids[$revision_id] = [
        'entity_id' => $id,
        'entity_revision_id' => $revision_id,
      ];
    }

    return $ids;
  }

  /**
   * Resolves all the links in one event.
   *
   * This approach is for legacy reasons to prevent BC and allow modules that
   * may have subscribed to this event to subscribe instead to
   * ManualLinkResolverEvent.
   *
   * @param array $link_entities
   *   The link entities.
   *
   * @return \Drupal\oe_link_lists\LinkCollectionInterface
   *   The list of resolved links.
   */
  protected function legacyResolveLinks(array $link_entities) {
    // @phpstan-ignore-next-line
    $event = new ManualLinksResolverEvent($link_entities);
    // @phpstan-ignore-next-line
    $this->eventDispatcher->dispatch($event, ManualLinksResolverEvent::NAME);
    return $event->getLinks();
  }

}
