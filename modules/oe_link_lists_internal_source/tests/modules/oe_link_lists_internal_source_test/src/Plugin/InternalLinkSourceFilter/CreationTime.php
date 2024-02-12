<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source_test\Plugin\InternalLinkSourceFilter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_link_lists_internal_source\InternalLinkSourceFilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test implementation of an internal link source filter.
 *
 * @InternalLinkSourceFilter(
 *   id = "creation_time",
 *   label = @Translation("Creation time"),
 *   description = @Translation("Filter on creation time."),
 * )
 */
class CreationTime extends InternalLinkSourceFilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * CreationTime constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->state = $state;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'creation' => 'all',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(string $entity_type, string $bundle): bool {
    $allowed_entity_types = $this->state->get('internal_source_test_creation_time_applicable_entity_types', []);

    return isset($allowed_entity_types[$entity_type]) && in_array($bundle, $allowed_entity_types[$entity_type]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['creation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Entity creation time'),
      '#options' => [
        'all' => $this->t('All'),
        'old' => $this->t('Old'),
      ],
      '#default_value' => $this->configuration['creation'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['creation'] = $form_state->getValue('creation');
  }

  /**
   * {@inheritdoc}
   */
  public function apply(QueryInterface $query, array $context, RefinableCacheableDependencyInterface $cacheability): void {
    // Allow to verify the context passed in tests.
    $this->state->set('internal_source_test_creation_time_context', $context);

    if ($this->configuration['creation'] === 'old') {
      // Show only content created one year ago.
      $query->condition('created', $this->time->getRequestTime() - 1 * 12 * 365 * 24 * 60 * 60, '<');
    }
  }

}
