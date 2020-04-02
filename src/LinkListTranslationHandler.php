<?php

namespace Drupal\oe_link_lists;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for link lists.
 */
class LinkListTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);

    if (isset($form['content_translation'])) {
      // We do not need to show these values on forms: they inherit the
      // basic link list property values.
      $form['content_translation']['status']['#access'] = FALSE;
      $form['content_translation']['created']['#access'] = FALSE;
    }

    $form_object = $form_state->getFormObject();
    $form_langcode = $form_object->getFormLangcode($form_state);
    $translations = $entity->getTranslationLanguages();
    $status_translatable = NULL;
    // Change the submit button labels if there was a status field they affect
    // in which case their publishing / unpublishing may or may not apply
    // to all translations.
    if (!$entity->isNew() && (!isset($translations[$form_langcode]) || count($translations) > 1)) {
      foreach ($entity->getFieldDefinitions() as $property_name => $definition) {
        if ($property_name === 'status') {
          $status_translatable = $definition->isTranslatable();
        }
      }
      if (isset($status_translatable) && isset($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] .= ' ' . ($status_translatable ? t('(this translation)') : t('(all translations)'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    if ($form_state->hasValue('content_translation')) {
      $translation = &$form_state->getValue('content_translation');
      $translation['status'] = $entity->isPublished();
      $translation['created'] = $this->dateFormatter->format($entity->created->value, 'custom', 'Y-m-d H:i:s O');
    }
    parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
  }

}
