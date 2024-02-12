<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source_test\Plugin\InternalLinkSourceFilter;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginBase;

/**
 * Test implementation of an internal link source filter.
 *
 * @InternalLinkSourceFilter(
 *   id = "first_letter",
 *   label = @Translation("First letter"),
 *   description = @Translation("Filters on the first letter of the name field."),
 *   entity_types = {
 *     "user" = {},
 *     "entity_test" = {}
 *   }
 * )
 */
class FirstLetter extends InternalLinkSourceFilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'first_letter' => 'a',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['first_letter'] = [
      '#type' => 'select',
      '#title' => $this->t('Name starts with'),
      '#options' => [
        'a' => 'A',
        'b' => 'B',
      ],
      '#default_value' => $this->configuration['first_letter'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['first_letter'] = $form_state->getValue('first_letter');
  }

  /**
   * {@inheritdoc}
   */
  public function apply(QueryInterface $query, array $context, RefinableCacheableDependencyInterface $cacheability): void {
    $query->condition('name', $this->configuration['first_letter'], 'STARTS_WITH');
  }

}
