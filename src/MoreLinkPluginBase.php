<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for more_link plugins.
 */
abstract class MoreLinkPluginBase extends PluginBase implements MoreLinkInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['no_configuration'] = [
      '#markup' => $this->t('This plugin does not have any configuration options.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Empty in many cases.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Empty in many cases.
  }

  /**
   * Adds "Override title" form fields to the configuration form.
   *
   * @param array $form
   *   Existing configuration form.
   * @param array $configuration
   *   Plugin configuration.
   */
  public function buildTitleOverrideForm(array &$form, array $configuration): void {
    $form['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override the link label. Defaults to "See all" or the referenced entity label.'),
      '#default_value' => isset($configuration['title_override']) && !is_null($configuration['title_override']),
    ];

    $parents = $form['#parents'];
    $first_parent = array_shift($parents);
    $title_override_name = $first_parent . '[' . implode('][', array_merge($parents, [
      'override',
    ])) . ']';

    $form['title_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('More link label'),
      '#default_value' => $configuration['title_override'] ?? '',
      '#element_validate' => [[get_class($this), 'validateMoreLinkOverride']],
      '#states' => [
        'visible' => [
          'input[name="' . $title_override_name . '"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="' . $title_override_name . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Validates the more link override is there if the checkbox is checked.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateMoreLinkOverride(array $element, FormStateInterface $form_state): void {
    $title = trim($element['#value']);
    if ($title !== '') {
      // If we have an override, nothing to validate.
      return;
    }

    $override_parents = array_merge(
      array_slice($element['#parents'], 0, -1),
      ['override']
    );
    $more_title_override = $form_state->getValue($override_parents);
    if ((bool) $more_title_override) {
      $form_state->setError($element, t('The "More link" label is required if you want to override the "More link" title.'));
    }
  }

}
