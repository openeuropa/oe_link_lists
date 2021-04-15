<?php

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_translatable_form",
 *   label = @Translation("Translatable form"),
 *   description = @Translation("A display that comes with a translatable form."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class TranslatableFormTestDisplay extends LinkTagTestDisplay implements TranslatableLinkListPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'translatable_string' => '',
      'non_translatable_string' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['translatable_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The display translatable string'),
      '#default_value' => $this->configuration['translatable_string'],
      '#required' => TRUE,
    ];

    $form['non_translatable_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The display non translatable string'),
      '#default_value' => $this->configuration['non_translatable_string'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['translatable_string'] = $form_state->getValue('translatable_string');
    $this->configuration['non_translatable_string'] = $form_state->getValue('non_translatable_string');
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      [
        'translatable_string',
      ],
    ];
  }

}
