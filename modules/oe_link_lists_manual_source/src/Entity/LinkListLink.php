<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\oe_link_lists\LinkInterface;
use Drupal\oe_link_lists_manual_source\Event\ManualLinkResolverEvent;

/**
 * Defines the LinkListLink entity.
 *
 * @ingroup oe_link_lists
 *
 * @ContentEntityType(
 *   id = "link_list_link",
 *   label = @Translation("Link list link"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\oe_link_lists_manual_source\LinkListLinkListBuilder",
 *     "access" = "Drupal\oe_link_lists_manual_source\LinkListLinkAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\oe_link_lists_manual_source\Form\LinkListLinkForm",
 *       "add" = "Drupal\oe_link_lists_manual_source\Form\LinkListLinkForm",
 *       "edit" = "Drupal\oe_link_lists_manual_source\Form\LinkListLinkForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "link_list_link",
 *   data_table = "link_list_link_field_data",
 *   revision_table = "link_list_link_revision",
 *   revision_data_table = "link_list_link_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer link list link entities",
 *   entity_revision_parent_type_field = "parent_type",
 *   entity_revision_parent_id_field = "parent_id",
 *   entity_revision_parent_field_name_field = "parent_field_name",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "bundle",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   bundle_entity_type = "link_list_link_type",
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/link_list_link/add/{link_list_link_type}",
 *     "add-page" = "/admin/content/link_list_link/add",
 *     "edit-form" = "/admin/content/link_list_link/{link_list_link}/edit",
 *     "delete-form" = "/admin/content/link_list_link/{link_list_link}/delete",
 *     "drupal:content-translation-overview" = "/admin/content/link_list_link/translations"
 *   },
 *   bundle_entity_type = "link_list_link_type",
 *   field_ui_base_route = "entity.link_list_link_type.edit_form",
 *   constraints = {
 *     "LinkListLinkFieldsRequired" = {}
 *   }
 * )
 */
class LinkListLink extends EditorialContentEntityBase implements LinkListLinkInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    if ($this->bundle() === 'external') {
      return $this->t('External link to: @external_url', ['@external_url' => $this->get('url')->uri]);
    }

    $target = $this->hasField('target') ? $this->get('target')->entity : NULL;
    if ($target instanceof NodeInterface) {
      return $this->t('Internal link to: @internal_entity', ['@internal_entity' => $target->label()]);
    }

    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event = new ManualLinkResolverEvent($this);
    $event_dispatcher->dispatch(ManualLinkResolverEvent::NAME, $event);
    $link = $event->hasLink() ? $event->getLink() : NULL;
    $bundle = \Drupal::entityTypeManager()->getStorage('link_list_link_type')->load($this->bundle())->label();
    $title = $link instanceof LinkInterface ? $link->getTitle() : $this->t('Unresolved');
    return $this->t('@bundle link: @title', ['@bundle' => $bundle, '@title' => $title]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): LinkListLinkInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTeaser(): ?string {
    return $this->get('teaser')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTeaser(string $teaser): LinkListLinkInterface {
    $this->set('teaser', $teaser);
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
  public function setTitle(string $title): LinkListLinkInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(ContentEntityInterface $parent, $parent_field_name) {
    $this->set('parent_type', $parent->getEntityTypeId());
    $this->set('parent_id', $parent->id());
    $this->set('parent_field_name', $parent_field_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the link.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['teaser'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Teaser'))
      ->setDescription(t('The teaser of the link.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'max_length' => 2000,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['parent_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent ID'))
      ->setDescription(t('The ID of the parent entity of which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setRevisionable(TRUE);

    $fields['parent_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent type'))
      ->setDescription(t('The entity parent type to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setDefaultValue('link_list')
      ->setRevisionable(TRUE);

    $fields['parent_field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent field name'))
      ->setDescription(t('The entity parent field name to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', FieldStorageConfig::NAME_MAX_LENGTH)
      ->setDefaultValue('links')
      ->setRevisionable(TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Link list link is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 10,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
