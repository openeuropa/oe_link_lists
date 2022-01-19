<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "configurable_non_translatable_test_source",
 *   label = @Translation("Configurable non translatable source"),
 *   description = @Translation("Complex Form Source description."),
 *   bundles = { "dynamic" }
 * )
 */
class ConfigurableNonTranslatableTestSource extends ExampleTestSource {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'non_translatable_string' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['non_translatable_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The source non translatable string'),
      '#default_value' => $this->configuration['non_translatable_string'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['non_translatable_string'] = $form_state->getValue([
      'non_translatable_string',
    ]);
  }

}
