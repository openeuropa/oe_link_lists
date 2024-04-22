<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\MoreLink;

use Drupal\Core\Form\FormStateInterface;

/**
 * Returns a simple hardcoded link..
 *
 * @MoreLink(
 *   id = "configurable_non_translatable_link",
 *   label = @Translation("Configurable non translatable more link"),
 *   description = @Translation("Returns a hardcoded link.")
 * )
 */
class ConfigurableNonTranslatableMoreLink extends HardcodedLink {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'more_link_configuration' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['more_link_configuration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The more link configuration'),
      '#default_value' => $this->configuration['more_link_configuration'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['more_link_configuration'] = $form_state->getValue([
      'more_link_configuration',
    ]);
  }

}
