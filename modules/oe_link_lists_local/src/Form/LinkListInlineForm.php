<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_local\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\Form\LinkListInlineForm as OriginalLinkListInlineForm;

/**
 * Link list inline entity form (IEF) handler.
 */
class LinkListInlineForm extends OriginalLinkListInlineForm {

  /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    $local = $this->isLocal($entity_form, $form_state);
    if (!$local) {
      return $entity_form;
    }

    // We do not want to show the administrative title on local link lists
    // because they are not supposed to be "administrated". Instead, we will
    // copy over the value from the link list title or generate one.
    $entity_form['administrative_title']['#access'] = FALSE;

    return $entity_form;
  }

  /**
   * Determines if a given field definition is a local link list reference.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   Whether it's local or not.
   */
  public static function isFieldLocal(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition instanceof FieldConfigInterface) {
      // This works for both configurable fields, as well as base field
      // overrides.
      return $field_definition->getThirdPartySetting('oe_link_lists_local', 'local', FALSE);
    }

    if ($field_definition instanceof BaseFieldDefinition) {
      $settings = $field_definition->getSetting('oe_link_lists_local');
      return $settings['local'] ?? FALSE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $entity_form, ContentEntityInterface $entity, FormStateInterface $form_state) {
    parent::buildEntity($entity_form, $entity, $form_state);
    $local = $this->isLocal($entity_form, $form_state);
    $entity->set('local', $local);

    // Set an administrative title automatically if one was not already set.
    $admin_title = $entity->label();
    if (!$admin_title) {
      $title = $entity->getTitle() ?? base64_encode(random_bytes(10));
      $entity->set('administrative_title', $title);
    }
  }

  /**
   * Determines if the link list reference field is marked as local.
   *
   * @param array $entity_form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   Whether it's local or not.
   */
  protected function isLocal(array $entity_form, FormStateInterface $form_state): bool {
    $ief_id = $entity_form['#ief_id'];
    $field = $form_state->get(['inline_entity_form', $ief_id, 'instance']);
    return static::isFieldLocal($field);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLabel(EntityInterface $entity) {
    // Since local link lists don't have a human-readable title, we need to
    // NULL this so our machine generated name doesn't show up in the remove
    // form.
    $is_local = (bool) $entity->get('local')->value;
    if ($is_local) {
      return NULL;
    }

    return parent::getEntityLabel($entity);
  }

}
