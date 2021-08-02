<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "test_complex_form",
 *   label = @Translation("Complex form"),
 *   description = @Translation("Complex Form Source description."),
 *   bundles = { "dynamic" }
 * )
 */
class ComplexFormTestSource extends ExampleTestSource implements TranslatableLinkListPluginInterface {

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
    $form['complex_form'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('The complex form parent'),
    ];

    $form['complex_form']['translatable_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The source translatable string'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['translatable_string'],
    ];

    $form['complex_form']['non_translatable_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The source non translatable string'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['non_translatable_string'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['translatable_string'] = $form_state->getValue([
      'complex_form',
      'translatable_string',
    ]);
    $this->configuration['non_translatable_string'] = $form_state->getValue([
      'complex_form',
      'non_translatable_string',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      [
        'complex_form',
        'translatable_string',
      ],
    ];
  }

}
