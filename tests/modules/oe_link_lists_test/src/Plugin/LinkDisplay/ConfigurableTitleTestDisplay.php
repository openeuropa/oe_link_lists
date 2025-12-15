<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Plugin implementation of the link_display.
 *
 * @LinkDisplay(
 *   id = "test_configurable_title",
 *   label = @Translation("Titles with optional link"),
 *   description = @Translation("Displays the list as titles, optionally linked to their source."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class ConfigurableTitleTestDisplay extends LinkDisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'link' => TRUE,
      'no_validate' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link'),
      '#default_value' => $this->configuration['link'],
    ];

    // Textfield that will prevent validation if filled in.
    $form['no_validate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No validate'),
      '#default_value' => $this->configuration['no_validate'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if ($form_state->getValue('no_validate')) {
      $form_state->setError($form, $this->t('The no_validate value cannot be filled in.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['link'] = $form_state->getValue('link');
  }

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $items = [];
    foreach ($links as $link) {
      if ($this->configuration['link']) {
        $items[] = Link::fromTextAndUrl($link->getTitle(), $link->getUrl());
        continue;
      }

      $items[] = $link->getTitle();

    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

}
