<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_internal_source\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the Internal Link Lists source plugin settings.
 */
class LinkListsInternalSourceSettings extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, EntityTypeManagerInterface $entit_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entit_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_link_lists_internal_source_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_link_lists_internal_source.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      // Skip bundleable entities that have no bundles declared.
      if (empty($bundle_info)) {
        continue;
      }

      $entity_types[$entity_type_id] = $entity_type->getLabel();
    }

    $config = $this->config('oe_link_lists_internal_source.settings');

    $form['allowed_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types'),
      '#required' => TRUE,
      '#options' => $entity_types,
      '#default_value' => array_keys($config->get('allowed_entity_bundles') ?? []),
      '#empty_value' => '',
      '#description' => $this->t('Select the entity types which can be used by the Internal source plugin.'),
    ];

    $form['allowed_bundles'] = [
      '#tree' => TRUE,
      '#type' => 'container',
    ];

    foreach ($entity_types as $entity_type_id => $entity_type_label) {
      $entity_type_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      $bundle_options = [];
      foreach ($entity_type_bundles as $bundle_id => $entity_type_bundle) {
        $bundle_options[$bundle_id] = $entity_type_bundle['label'];
      }
      $form['allowed_bundles'][$entity_type_id] = [
        '#title' => $this->t('Bundles of %entity_type', ['%entity_type' => $entity_type_label]),
        '#type' => 'details',
        '#open' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="allowed_entity_types[' . $entity_type_id . ']"]' => ['checked' => TRUE],
          ],
        ],
        'bundles' => [
          '#title_display' => 'invisible',
          '#title' => $this->t('Bundles of %entity_type', ['%entity_type' => $entity_type_label]),
          '#type' => 'checkboxes',
          '#options' => $bundle_options,
          '#default_value' => $config->get('allowed_entity_bundles.' . $entity_type_id) ?? [],
          '#description' => $this->t('Select the entity bundles of @entity_type which can be used by the Internal source plugin.', ['@entity_type' => $entity_type_label]),
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected_entity_types = array_filter($form_state->getValue('allowed_entity_types'));
    foreach ($selected_entity_types as $entity_type) {
      $selected_bundles = array_filter($form_state->getValue([
        'allowed_bundles',
        $entity_type,
        'bundles',
      ]));

      if (empty($selected_bundles)) {
        $definition = $this->entityTypeManager->getDefinition($entity_type);
        $form_state->setError($form['allowed_bundles'][$entity_type]['bundles'], $this->t('Please select at least 1 bundle for %entity_type. Or select all of them if you would like all to be included.', ['%entity_type' => $definition->getLabel()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('oe_link_lists_internal_source.settings');
    $selected_entity_types = array_filter($form_state->getValue('allowed_entity_types'));
    $allowed_bundles = [];
    foreach ($selected_entity_types as $entity_type) {
      $selected_bundles = array_filter($form_state->getValue([
        'allowed_bundles',
        $entity_type,
        'bundles',
      ]));

      $allowed_bundles[$entity_type] = array_keys($selected_bundles);
    }

    $config->set('allowed_entity_bundles', $allowed_bundles)->save();
    parent::submitForm($form, $form_state);
  }

}
