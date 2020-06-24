<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_internal_source\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenEuropa Internal Link Lists settings for internal source plugin.
 */
class LinkListsInternalSourceSettings extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entit_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entit_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
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
      '#description' => $this->t('Please, select entity types which should be allowed for selecting for Link List internal source.'),
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
        '#title' => $this->t('Bundles of %entity_type entity type', ['%entity_type' => $entity_type_label]),
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
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('oe_link_lists_internal_source.settings');
    $allowed_entity_types = array_filter($form_state->getValue('allowed_entity_types'));
    $allowed_bundles = [];
    foreach ($allowed_entity_types as $entity_type) {
      $selected_allowed_bundles = array_filter($form_state->getValue([
        'allowed_bundles',
        $entity_type,
        'bundles',
      ]));
      // Allowed bundles should be explicitly selected.
      if (empty($selected_allowed_bundles)) {
        continue;
      }
      $allowed_bundles[$entity_type] = $selected_allowed_bundles;
    }
    $config->set('allowed_entity_bundles', $allowed_bundles)->save();
    parent::submitForm($form, $form_state);
  }

}
