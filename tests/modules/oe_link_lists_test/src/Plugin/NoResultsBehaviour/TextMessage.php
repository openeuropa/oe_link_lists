<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\NoResultsBehaviour;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\NoResultsBehaviourPluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;

/**
 * Shows a simple text message when there are no results.
 *
 * @NoResultsBehaviour(
 *   id = "text_message",
 *   label = @Translation("Text message"),
 *   description = @Translation("Shows a simple text message.")
 * )
 */
class TextMessage extends NoResultsBehaviourPluginBase implements TranslatableLinkListPluginInterface {

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
      '#title' => $this->t('The message you want shown'),
      '#required' => TRUE,
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

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      [
        'text',
      ],
    ];
  }

}
