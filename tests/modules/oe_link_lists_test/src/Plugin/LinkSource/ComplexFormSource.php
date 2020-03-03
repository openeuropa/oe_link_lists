<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "complex_form",
 *   label = @Translation("Complex Form Source"),
 *   description = @Translation("Complex Form Source description.")
 * )
 */
class ComplexFormSource extends LinkSourcePluginBase implements TranslatableLinkListPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $links = [
      new DefaultLink(Url::fromUri('http://example.com'), 'Example', ['#markup' => 'Example teaser']),
      new DefaultLink(Url::fromUri('http://ec.europa.eu'), 'European Commission', ['#markup' => 'European teaser']),
      new DefaultLink(Url::fromUri('https://ec.europa.eu/info/departments/informatics_en'), 'DIGIT', ['#markup' => 'Informatics teaser']),
    ];

    if ($limit) {
      $links = array_slice($links, $offset, $limit);
    }

    return new LinkCollection($links);
  }

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
    $this->configuration['translatable_string'] = $form_state->getValue(['complex_form', 'translatable_string']);
    $this->configuration['non_translatable_string'] = $form_state->getValue(['complex_form', 'non_translatable_string']);
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
