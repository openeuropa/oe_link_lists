<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Callback for inline entity forms for link list link entities.
 */
class LinkListLinkInlineForm extends EntityInlineForm {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);

    if (isset($entity_form['revision_log'])) {
      $entity_form['revision_log']['#access'] = FALSE;
    }
    if (isset($entity_form['status'])) {
      $entity_form['status']['#access'] = FALSE;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_form['#entity'];

    // Apply a "required" #states condition to the title and teaser elements in
    // case the URL field exists and it is filled in.
    if ($entity->bundle() === 'external' && isset($entity_form['url'])) {
      $parents = $entity_form['url']['widget'][0]['#field_parents'];
      $first = array_shift($parents);
      $parents = array_merge($parents, ['url', 0, 'uri']);
      $name = $first . '[' . implode('][', $parents) . ']';
      $states = [
        'required' => [
          'input[name="' . $name . '"]' => ['filled' => TRUE],
        ],
      ];

      foreach (['title', 'teaser'] as $name) {
        $entity_form[$name]['widget'][0]['value']['#states'] = $states;
      }
    }

    if ($entity->hasField('target')) {
      $this->addOverrideElement($entity_form, $form_state);
    }

    return $entity_form;
  }

  /**
   * Adds the "override" checkbox to the Internal links form.
   *
   * @param array $entity_form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function addOverrideElement(array &$entity_form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $entity */
    $entity = $entity_form['#entity'];

    $entity_form['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override target values'),
      '#default_value' => ($entity->getTitle() || $entity->getTeaser()) ? TRUE : FALSE,
      '#weight' => 1,
    ];

    // For the Internal bundle, we only want to show the Title and Teaser
    // elements if the Override checkbox is checked. So we use #states for
    // controlling the visibility.
    $parents = $entity_form['#parents'];
    $first = array_shift($parents);
    $parents = array_merge($parents, ['override']);
    $name = $first . '[' . implode('][', $parents) . ']';
    foreach (['title', 'teaser'] as $field) {
      $entity_form[$field]['#states'] = [
        'visible' => [
          'input[name="' . $name . '"]' => ['checked' => TRUE],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntity(array $entity_form, ContentEntityInterface $entity, FormStateInterface $form_state) {
    parent::buildEntity($entity_form, $entity, $form_state);

    if (!isset($entity_form['override'])) {
      return;
    }

    // @todo use the form state to get the checked override value when the
    // manual links are no longer mangled in the plugin form.
    $override = (bool) $entity_form['override']['#value'];
    if (!$override) {
      $entity->set('title', NULL);
      $entity->set('teaser', NULL);
    }
  }

}
