<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\oe_link_lists\LinkListConfigurationManager;

/**
 * Defines the LinkList entity.
 *
 * @ingroup oe_link_lists
 *
 * @ContentEntityType(
 *   id = "link_list",
 *   label = @Translation("Link list"),
 *   bundle_label = @Translation("Link list type"),
 *   handlers = {
 *     "view_builder" = "Drupal\oe_link_lists\LinkListViewBuilder",
 *     "list_builder" = "Drupal\oe_link_lists\LinkListListBuilder",
 *     "access" = "Drupal\oe_link_lists\LinkListAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "translation" = "Drupal\oe_link_lists\LinkListTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\oe_link_lists\Form\LinkListForm",
 *       "add" = "Drupal\oe_link_lists\Form\LinkListForm",
 *       "edit" = "Drupal\oe_link_lists\Form\LinkListForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\oe_link_lists\Routing\LinkListRouteProvider",
 *     },
 *   },
 *   base_table = "link_list",
 *   data_table = "link_list_field_data",
 *   revision_table = "link_list_revision",
 *   revision_data_table = "link_list_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer link_lists",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "administrative_title",
 *     "bundle" = "bundle",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *     "created" = "created",
 *     "changed" = "changed",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "add-form" = "/link_list/add/{link_list_type}",
 *     "add-page" = "/link_list/add",
 *     "canonical" = "/link_list/{link_list}",
 *     "collection" = "/admin/content/link_lists",
 *     "edit-form" = "/link_list/{link_list}/edit",
 *     "delete-form" = "/link_list/{link_list}/delete",
 *     "delete-multiple-form" = "/admin/content/link_list/delete",
 *   },
 *   bundle_entity_type = "link_list_type",
 *   field_ui_base_route = "entity.link_list_type.edit_form"
 * )
 */
class LinkList extends EditorialContentEntityBase implements LinkListInterface {

  /**
   * {@inheritdoc}
   */
  public function getAdministrativeTitle(): string {
    return (string) $this->get('administrative_title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdministrativeTitle(string $administrative_title): LinkListInterface {
    $this->set('administrative_title', $administrative_title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->getConfigurationManager()->getConfiguration($this->get('configuration')->first());
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): LinkListInterface {
    $this->getConfigurationManager()->setConfiguration($configuration, $this->get('configuration')->first());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): ?string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): LinkListInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): LinkListInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    parent::save();

    // Invalidate the block cache to update the derivatives.
    \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();

    // Invalidate the block cache to update the derivatives.
    \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $config = $this->getConfiguration();

    // If the link list type works automatically with a given link source
    // plugin, configure it if it's missing.
    if (!isset($config['source'])) {
      $bundle = LinkListType::load($this->bundle());
      $auto_plugin = $bundle->getDefaultLinkSource();
      if ($auto_plugin) {
        $config['source'] = [
          'plugin' => $auto_plugin,
          'plugin_configuration' => [],
        ];
        $this->setConfiguration($config);
      }
    }

    if (isset($config['source']['plugin']) && isset($config['source']['plugin_configuration'])) {
      /** @var \Drupal\oe_link_lists\LinkSourcePluginManager $source_plugin_manager */
      $source_plugin_manager = \Drupal::service('plugin.manager.oe_link_lists.link_source');
      $plugin = $source_plugin_manager->createInstance($config['source']['plugin'], $config['source']['plugin_configuration']);
      $plugin->preSave($this);
    }

    if (isset($config['display']['plugin']) && isset($config['display']['plugin_configuration'])) {
      /** @var \Drupal\oe_link_lists\LinkDisplayPluginManager $display_plugin_manager */
      $display_plugin_manager = \Drupal::service('plugin.manager.oe_link_lists.link_display');
      $plugin = $display_plugin_manager->createInstance($config['display']['plugin'], $config['display']['plugin_configuration']);
      $plugin->preSave($this);
    }
  }

  /**
   * Returns the configuration manager for link lists.
   *
   * @return \Drupal\oe_link_lists\LinkListConfigurationManager
   *   The configuration manager.
   */
  protected function getConfigurationManager(): LinkListConfigurationManager {
    return \Drupal::service('oe_link_list.link_list_configuration_manager');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['administrative_title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Administrative title'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['configuration'] = BaseFieldDefinition::create('link_list_configuration')
      ->setLabel(t('Configuration'))
      ->setDescription(t('The list configuration.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'link_list_configuration',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDefaultValue([]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the list was created.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the list was last edited.'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
