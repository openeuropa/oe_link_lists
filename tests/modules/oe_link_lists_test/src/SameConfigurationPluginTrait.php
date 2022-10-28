<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test;

use Drupal\Core\Form\FormStateInterface;

/**
 * Test trait for the plugins with the same configuration form.
 */
trait SameConfigurationPluginTrait {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => t('The value'),
      '#default_value' => $this->configuration['value'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['value'] = $form_state->getValue(['value']);
  }

}
