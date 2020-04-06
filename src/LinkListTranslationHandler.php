<?php

namespace Drupal\oe_link_lists;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for link lists.
 *
 * Map the status and created field values from the entity to the content
 * translation.
 */
class LinkListTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);

    if (isset($form['content_translation'])) {
      $form['content_translation']['status']['#access'] = FALSE;
      $form['content_translation']['created']['#access'] = FALSE;
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
