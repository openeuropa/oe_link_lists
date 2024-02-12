<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\NoResultsBehaviour;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\NoResultsBehaviourPluginBase;

/**
 * Test plugin with a non-translatable configurable string..
 *
 * @NoResultsBehaviour(
 *   id = "non_translatable_text_message",
 *   label = @Translation("Non translatable text message"),
 *   description = @Translation("Shows a simple text message.")
 * )
 */
class ConfigurableNonTranslatableNoResultsMessage extends NoResultsBehaviourPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'text' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(LinkListInterface $link_list): array {
    return [
      '#markup' => $this->configuration['text'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The non-translatable message you want shown'),
      '#default_value' => $this->configuration['text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['text'] = $form_state->getValue('text');
  }

}
