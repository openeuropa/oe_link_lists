<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_group;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class to attach Link list To Group.
 */
class AttachLinkListToGroup {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The group relation type manager.
   *
   * @var \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface
   */
  protected $groupRelationTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Group relationship storage.
   *
   * @var \Drupal\group\Entity\Storage\GroupRelationshipStorage
   */
  protected $groupRelationshipStorage;

  /**
   * Link list logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Link list item group counts.
   *
   * @var array
   */
  protected $groupCount = [];

  /**
   * AttachLinkListToGroup constructor.
   *
   * @param \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface $group_relationship_type_manager
   *   The group relation type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    GroupRelationTypeManagerInterface $group_relationship_type_manager,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    $this->groupRelationTypeManager = $group_relationship_type_manager;
    $this->moduleHandler = $module_handler;
    $this->groupRelationshipStorage = $entity_type_manager->getStorage('group_content');
    $this->logger = $logger;
  }

  /**
   * Attach link list items from given entity to the same group(s).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to process.
   */
  public function attach(EntityInterface $entity): void {
    $groups = $this->getGroups($entity);
    if (empty($groups)) {
      return;
    }

    $items = $this->getLinkListFromEntity($entity);
    if (empty($items)) {
      return;
    }

    $this->assignLinkListsToGroups($items, $groups);
  }

  /**
   * Assign link list items to groups.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface[] $link_list_items
   *   List of link list items to assign.
   * @param \Drupal\group\Entity\GroupInterface[] $groups
   *   List of groups to assign link lists.
   */
  public function assignLinkListsToGroups(array $link_list_items, array $groups): void {
    $link_list_plugins_cache = [];

    // Get the list of installed group relationship instance IDs.
    $group_type_plugin_map = $this->groupRelationTypeManager->getGroupTypePluginMap();
    $group_relationship_instance_ids = [];

    foreach ($group_type_plugin_map as $plugins) {
      $group_relationship_instance_ids = array_merge(
        $group_relationship_instance_ids,
        $plugins
      );
    }

    foreach ($link_list_items as $link_list_item) {
      // Build the instance ID.
      $plugin_id = 'group_link_list:' . $link_list_item->bundle();

      // Check if this link list type should be group relationship or not.
      if (!in_array($plugin_id, $group_relationship_instance_ids)) {
        $this->logger->debug($this->t('Link list @label (@id) was not assigned to any group because its bundle (@name) is not enabled in any group', [
          '@label' => $link_list_item->label(),
          '@id' => $link_list_item->id(),
          '@name' => $link_list_item->bundle(),
        ]));
        continue;
      }

      foreach ($groups as $group) {
        if (!isset($link_list_plugins_cache[$plugin_id])) {
          $link_list_plugins_cache[$plugin_id] = $this->getLinkListGroupRelationshipEnablerPlugin($group, $plugin_id);
        }

        $plugin = $link_list_plugins_cache[$plugin_id];
        if (empty($plugin)) {
          continue;
        }

        $group_cardinality = $plugin->getGroupCardinality();
        $group_count = $this->getGroupCount($link_list_item);

        // Check if group cardinality still allows to create relation.
        if ($group_cardinality == 0 || $group_count < $group_cardinality) {
          $group_relations = $group->getRelationshipsByEntity($link_list_item, $plugin_id);
          $entity_cardinality = $plugin->getEntityCardinality();
          // Add this link list as group relationship if cardinality allows.
          if ($entity_cardinality == 0 || count($group_relations) < $entity_cardinality) {
            $group->addRelationship($link_list_item, $plugin_id);
          }
          else {
            $this->logger->debug($this->t('Link list @label (@id) was not assigned to group @group_label because max entity cardinality was reached', [
              '@label' => $link_list_item->label(),
              '@id' => $link_list_item->id(),
              '@group_label' => $group->label(),
            ]));
          }
        }
        else {
          $this->logger->debug($this->t('Link list @label (@id) was not assigned to group @group_label because max group cardinality was reached', [
            '@label' => $link_list_item->label(),
            '@id' => $link_list_item->id(),
            '@group_label' => $group->label(),
          ]));
        }
      }
    }
  }

  /**
   * Gets link list items from a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object to search link list items in.
   *
   * @return \Drupal\oe_link_lists\Entity\LinkListInterface[]|array
   *   List of link list items found for given entity.
   */
  public function getLinkListFromEntity(EntityInterface $entity): array {
    $items = [];

    if ($entity instanceof ContentEntityInterface) {
      // Skip entity reference from Group relationship
      // link list, because we just added link list there.
      $is_link_list_group_relationship = $entity instanceof GroupRelationshipInterface && strpos($entity->getRelationshipType()->getPluginId(), 'group_link_list:') == 0;
      $allowed_field_types = ['entity_reference', 'entity_reference_revisions'];
      // Loop through all fields on the entity.
      foreach ($entity->getFieldDefinitions() as $key => $field) {
        // Check if the field is an entity reference, referencing link list
        // entities, and retriever the link list entity.
        if (
          !($key == 'entity_id' && $is_link_list_group_relationship)
          && in_array($field->getType(), $allowed_field_types)
          && $field->getSetting('target_type') == 'link_list'
          && !$entity->get($key)->isEmpty()
        ) {
          foreach ($entity->get($key)->getIterator() as $item) {
            if ($item->entity) {
              $items[] = $item->entity;
            }
          }
        }
      }
    }

    return $items;
  }

  /**
   * Gets the groups by entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to check.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Groups that the current entity belongs too.
   */
  public function getGroups(EntityInterface $entity): array {
    $groups = [];
    if ($entity instanceof GroupRelationshipInterface) {
      $groups[] = $entity->getGroup();
    }
    elseif ($entity instanceof GroupInterface) {
      $groups[] = $entity;
    }
    elseif ($entity instanceof ContentEntityInterface) {
      $group_relationships = $this->groupRelationshipStorage->loadByEntity($entity);
      foreach ($group_relationships as $group_relationship) {
        $groups[] = $group_relationship->getGroup();
      }
    }

    return $groups;
  }

  /**
   * Get link list group relationship type plugin.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param string $instance_id
   *   Instance id.
   *
   * @return \Drupal\group\Plugin\Group\Relation\GroupRelationInterface|null
   *   Link list group relation instance or null.
   */
  private function getLinkListGroupRelationshipEnablerPlugin(GroupInterface $group, $instance_id): ?GroupRelationInterface {
    $group_type_plugins = $this->groupRelationTypeManager->getInstalled($group->getGroupType());

    // Check if the group type supports the plugin of type $instance_id.
    if ($group_type_plugins->has($instance_id)) {
      return $group_type_plugins->get($instance_id);
    }

    return NULL;
  }

  /**
   * Get group count for link list item.
   *
   * @param Drupal\Core\Entity\EntityInterface $item
   *   Link list entity.
   *
   * @return int
   *   Group count.
   */
  private function getGroupCount(EntityInterface $item): int {
    // Check if it was calculated already.
    if (!isset($this->groupCount[$item->id()])) {
      // Check what relations already exist for this link list to control the
      // group cardinality.
      $group_relationships = $this->groupRelationshipStorage->loadByEntity($item);
      $group_ids = [];

      /** @var \Drupal\group\Entity\GroupRelationshipInterface $group_relationship */
      foreach ($group_relationships as $group_relationship) {
        $group_ids[] = $group_relationship->getGroup()->id();
      }
      $this->groupCount[$item->id()] = count(array_unique($group_ids));
    }

    return $this->groupCount[$item->id()];
  }

}
