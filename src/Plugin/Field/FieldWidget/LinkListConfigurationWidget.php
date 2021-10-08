<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\LinkDisplayPluginManagerInterface;
use Drupal\oe_link_lists\LinkListConfigurationManager;
use Drupal\oe_link_lists\LinkSourcePluginManagerInterface;
use Drupal\oe_link_lists\NoResultsBehaviourPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'link_list_configuration' field widget.
 *
 * This is used for building the form used to configure the link list.
 *
 * @FieldWidget(
 *   id = "link_list_configuration",
 *   label = @Translation("Link List Configuration"),
 *   field_types = {"link_list_configuration"},
 * )
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class LinkListConfigurationWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfoManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The link display plugin manager.
   *
   * @var \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface
   */
  protected $linkDisplayPluginManager;

  /**
   * The link source plugin manager.
   *
   * @var \Drupal\oe_link_lists\LinkSourcePluginManagerInterface
   */
  protected $linkSourcePluginManager;

  /**
   * The link list configuration manager.
   *
   * @var \Drupal\oe_link_lists\LinkListConfigurationManager
   */
  protected $linkListConfigurationManager;

  /**
   * The no_results_behaviour plugin manager.
   *
   * @var \Drupal\oe_link_lists\NoResultsBehaviourPluginManagerInterface
   */
  protected $noResultsBehaviourPluginManager;

  /**
   * Constructs a LinkListConfigurationWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\oe_link_lists\LinkSourcePluginManagerInterface $link_source_plugin_manager
   *   The link source plugin manager.
   * @param \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface $link_display_plugin_manager
   *   The link display plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info_manager
   *   The element info manager.
   * @param \Drupal\oe_link_lists\LinkListConfigurationManager $link_list_configuration_manager
   *   The link list configuration manager.
   * @param \Drupal\oe_link_lists\NoResultsBehaviourPluginManagerInterface $no_results_behaviour_manager
   *   The no_results_behaviour plugin manager.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, LinkSourcePluginManagerInterface $link_source_plugin_manager, LinkDisplayPluginManagerInterface $link_display_plugin_manager, EntityTypeManagerInterface $entity_type_manager, ElementInfoManagerInterface $element_info_manager, LinkListConfigurationManager $link_list_configuration_manager, NoResultsBehaviourPluginManagerInterface $no_results_behaviour_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->linkSourcePluginManager = $link_source_plugin_manager;
    $this->linkDisplayPluginManager = $link_display_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->elementInfoManager = $element_info_manager;
    $this->linkListConfigurationManager = $link_list_configuration_manager;
    $this->noResultsBehaviourPluginManager = $no_results_behaviour_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.oe_link_lists.link_source'),
      $container->get('plugin.manager.oe_link_lists.link_display'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.element_info'),
      $container->get('oe_link_list.link_list_configuration_manager'),
      $container->get('plugin.manager.oe_link_lists.no_results_behaviour')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $entity */
    $entity = $items->getEntity();
    $element['#translatable_parents'] = [];
    if (!$entity->isDefaultTranslation()) {
      $element['#translatable_parents'] = $this->linkListConfigurationManager->getTranslatableParents($items->first());
      $element['#process'][] = [get_class($this), 'processUntranslatableFields'];
    };

    $element['#type'] = 'container';
    if (!isset($element['#attributes']['id'])) {
      $wrapper_suffix = $element['#field_parents'] ? '-' . implode('-', $element['#field_parents']) : '';
      $element['#attributes'] = [
        'id' => 'link-list-element-wrapper-' . $wrapper_suffix,
      ];
    }

    $this->buildLinkSourceElements($items, $delta, $element, $form, $form_state);
    $this->buildLinkDisplayElements($items, $delta, $element, $form, $form_state);
    $this->buildNoResultsBehaviourElements($items, $delta, $element, $form, $form_state);

    return $element;
  }

  /**
   * Process callback for disabling non-translatable elements.
   *
   * This is applied for the main widget form as well as the individual plugin
   * (source and display) forms and is meant to recursively disable all the
   * form elements that are not translatable.
   *
   * When adding this callback, "#translatable_parents" needs to be set on the
   * element to inform where the translatable elements are located. See
   * LinkListConfigurationManager::getTranslatableParents().
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed element.
   */
  public static function processUntranslatableFields(array &$element, FormStateInterface $form_state): array {
    $translatable_parents = $element['#translatable_parents'] ?? [];
    if (!$translatable_parents) {
      return $element;
    }

    $parents = [];
    foreach (array_keys($translatable_parents) as $parents_string) {
      $parents[] = explode('][', $parents_string);
    }
    static::disableNonTranslatableElements($element, $parents);

    return $element;
  }

  /**
   * Disables the non-translatable form elements in a given form structure.
   *
   * Recursively goes through the form structure and uses the
   * $translatable_parents argument to determine which sub elements are
   * translatable and disables all the rest.
   *
   * @param array $element
   *   The form structure.
   * @param array $translatable_parents
   *   An array of parent sets which point to all the elements that are
   *   translatable.
   */
  protected static function disableNonTranslatableElements(array &$element, array $translatable_parents): void {
    foreach ($translatable_parents as $parent_set) {
      $sub_element = &NestedArray::getValue($element, $parent_set, $exists);
      if (!$exists) {
        continue;
      }

      $sub_element['#translatable'] = TRUE;
    }

    if (empty(Element::children($element)) && !isset($element['#translatable']) && !isset($element['#plugin'])) {
      $element['#disabled'] = TRUE;
      return;
    }

    foreach (Element::children($element) as $key) {
      static::disableNonTranslatableElements($element[$key], $translatable_parents);
    }
  }

  /**
   * Builds the link source plugin form elements.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param int $delta
   *   The item delta.
   * @param array $element
   *   The form element.
   * @param array $form
   *   The entire form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildLinkSourceElements(FieldItemListInterface $items, int $delta, array &$element, array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->getBuildInfo()['callback_object']->getEntity();

    // Only certain bundles expose the choice of the link source plugin.
    /** @var \Drupal\oe_link_lists\Entity\LinkListType $bundle */
    $bundle = $this->entityTypeManager->getStorage('link_list_type')->load($link_list->bundle());
    if (!$bundle->isLinkSourceConfigurable()) {
      return;
    }

    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'link_source',
    ]);

    $element['link_source'] = [
      '#type' => 'details',
      '#title' => $this->t('The source of the links'),
      '#open' => TRUE,
      '#element_parents' => $parents,
    ];

    $plugin_id = NestedArray::getValue($form_state->getStorage(), [
      'plugin_select',
      'link_source',
    ]);
    if (!$plugin_id) {
      $plugin_id = $this->getConfigurationPluginId($link_list, 'source');
    }

    $source_plugin_options = $this->linkSourcePluginManager->getPluginsAsOptions($link_list->bundle());

    // If we don't have a plugin ID and there is only one available option,
    // use that as the default.
    if (!$plugin_id && count($source_plugin_options) === 1) {
      $plugin_id = key($source_plugin_options);
    }

    $element['link_source']['plugin'] = [
      '#type' => 'select',
      '#title' => t('Link source'),
      '#empty_option' => $this->t('None'),
      '#empty_value' => '',
      '#options' => $source_plugin_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'pluginConfigurationAjaxCallback'],
        'wrapper' => $element['#attributes']['id'],
      ],
      '#default_value' => $plugin_id,
      '#submit' => [
        [get_class($this), 'selectPlugin'],
      ],
      '#executes_submit_callback' => TRUE,
      '#plugin_select' => 'link_source',
      '#limit_validation_errors' => [
        array_merge($parents, ['plugin']),
      ],
    ];

    // A wrapper that the Ajax callback will replace.
    $element['link_source']['plugin_configuration_wrapper'] = [
      '#type' => 'container',
      '#weight' => 10,
      '#tree' => TRUE,
    ];

    // If we have determined a plugin (either by way of default stored value or
    // user selection), create the form element for its configuration. For this
    // we pass potentially existing configuration to the plugin so that it can
    // use it in its form elements' default values.
    if ($plugin_id) {
      $existing_config = $this->getConfigurationPluginConfiguration($link_list, 'source');
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->linkSourcePluginManager->createInstance($plugin_id, $existing_config);

      $element['link_source']['plugin_configuration_wrapper'][$plugin_id] = [
        '#process' => [[get_class($this), 'processPluginConfiguration']],
        '#plugin' => $plugin,
      ];

      if (!empty($element['#translatable_parents'])) {
        // If we are translating the entity and we have elements that we are
        // translating, add a process to the plugin form to handle them.
        $process = [get_class($this), 'processUntranslatableFields'];
        $element['link_source']['plugin_configuration_wrapper'][$plugin_id]['#process'][] = $process;
        $element['link_source']['plugin_configuration_wrapper'][$plugin_id]['#translatable_parents'] = $element['#translatable_parents'];
      }
    }
  }

  /**
   * Builds the link source plugin form elements.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param int $delta
   *   The item delta.
   * @param array $element
   *   The form element.
   * @param array $form
   *   The entire form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildLinkDisplayElements(FieldItemListInterface $items, int $delta, array &$element, array &$form, FormStateInterface $form_state): void {
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'link_display',
    ]);

    $element['link_display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display options'),
      '#open' => TRUE,
    ];

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->getBuildInfo()['callback_object']->getEntity();
    $plugin_id = NestedArray::getValue($form_state->getStorage(), [
      'plugin_select',
      'link_display',
    ]);
    if (!$plugin_id) {
      $plugin_id = $this->getConfigurationPluginId($link_list, 'display');
    }

    // Now we need to determine what is the selected link source plugin. This
    // can be found in two ways: either from the form submission or to check
    // the current link list configuration.
    $link_source_plugin_id = $form_state->get([
      'plugin_select',
      'link_source',
    ]);
    if (!$link_source_plugin_id) {
      $link_source_plugin_id = $this->getConfigurationPluginId($link_list, 'link_source');
    }

    $display_plugin_options = $this->linkDisplayPluginManager->getPluginsAsOptions($link_list->bundle(), $link_source_plugin_id);

    // If we don't have a plugin ID and there is only one available option,
    // use that as the default.
    if (!$plugin_id && count($display_plugin_options) === 1) {
      $plugin_id = key($display_plugin_options);
    }

    if ($display_plugin_options) {
      $element['link_display']['plugin'] = [
        '#type' => 'select',
        '#title' => $this->t('Link display'),
        '#empty_option' => $this->t('None'),
        '#empty_value' => '',
        '#required' => TRUE,
        '#options' => $display_plugin_options,
        '#ajax' => [
          'callback' => [$this, 'pluginConfigurationAjaxCallback'],
          'wrapper' => $element['#attributes']['id'],
        ],
        '#submit' => [
          [get_class($this), 'selectPlugin'],
        ],
        '#default_value' => $plugin_id,
        '#executes_submit_callback' => TRUE,
        '#plugin_select' => 'link_display',
        '#limit_validation_errors' => [
          array_merge($parents, ['plugin']),
        ],
        '#access' => !empty($display_plugin_options),
      ];

      // A wrapper that the Ajax callback will replace.
      $element['link_display']['plugin_configuration_wrapper'] = [
        '#type' => 'container',
        '#weight' => 10,
        '#tree' => TRUE,
      ];
    }
    else {
      $element['link_display']['no_plugin'] = [
        '#markup' => $this->t('There are no display plugins available for your chosen link source.'),
      ];
    }

    if ($plugin_id) {
      $existing_config = $this->getConfigurationPluginConfiguration($link_list, 'display');
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->linkDisplayPluginManager->createInstance($plugin_id, $existing_config);

      $element['link_display']['plugin_configuration_wrapper'][$plugin_id] = [
        '#process' => [[get_class($this), 'processPluginConfiguration']],
        '#plugin' => $plugin,
      ];

      if (!empty($element['#translatable_parents'])) {
        // If we are translating the entity and we have elements that we are
        // translating, add a process to the plugin form to handle them.
        $process = [get_class($this), 'processUntranslatableFields'];
        $element['link_display']['plugin_configuration_wrapper'][$plugin_id]['#process'][] = $process;
        $element['link_display']['plugin_configuration_wrapper'][$plugin_id]['#translatable_parents'] = $element['#translatable_parents'];
      }
    }

    $this->buildGeneralConfigurationForm($items, $delta, $element, $form, $form_state);
  }

  /**
   * Builds the general configuration form.
   *
   * Configures the size of the list and "See all" button.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param int $delta
   *   The item delta.
   * @param array $element
   *   The form element.
   * @param array $form
   *   The entire form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildGeneralConfigurationForm(FieldItemListInterface $items, int $delta, array &$element, array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->getBuildInfo()['callback_object']->getEntity();
    $configuration = $link_list->getConfiguration();

    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'link_display',
    ]);
    $first_parent = array_shift($parents);

    $options = [0 => $this->t('All')];
    $range = range(1, 20);
    $options += array_combine($range, $range);

    $element['link_display']['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of items'),
      '#weight' => 10,
      '#options' => $options,
      '#default_value' => $configuration['size'] ?? 0,
    ];

    $name = $first_parent . '[' . implode('][', array_merge($parents, ['size'])) . ']';
    $element['link_display']['more'] = [
      '#type' => 'fieldset',
      '#weight' => 11,
      '#title' => $this->t('Display button to see all links'),
      '#states' => [
        'invisible' => [
          'select[name="' . $name . '"]' => ['value' => 0],
        ],
      ],
    ];

    $element['link_display']['more']['button'] = [
      '#type' => 'radios',
      '#title' => '',
      '#default_value' => $configuration['more']['button'] ?? 'no',
      '#options' => [
        'no' => $this->t('No, do not display "See all" button'),
        'custom' => $this->t('Yes, display a custom button'),
      ],
    ];

    $default_target = '';
    if (isset($configuration['more']['target'])) {
      if ($configuration['more']['target']['type'] === 'entity') {
        if ($entity = $this->entityTypeManager->getStorage($configuration['more']['target']['entity_type'])->load($configuration['more']['target']['entity_id'])) {
          $default_target = EntityAutocomplete::getEntityLabels([$entity]);
        }
      }
      if ($configuration['more']['target']['type'] === 'custom') {
        $default_target = $configuration['more']['target']['url'];
      }
    }

    // This element behaves like an entity autocomplete form element but has
    // extra custom validation to allow any routes to be specified.
    $name = $first_parent . '[' . implode('][', array_merge($parents, [
      'more',
      'button',
    ])) . ']';
    $element['link_display']['more']['more_target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target'),
      '#description' => $this->t('This can be an external link or you can autocomplete to find internal content.'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#autocreate' => FALSE,
      '#process' => $this->elementInfoManager->getInfoProperty('entity_autocomplete', '#process'),
      '#default_value' => $default_target,
      '#element_validate' => [[get_class($this), 'validateMoreTarget']],
      '#states' => [
        'visible' => [
          'input[name="' . $name . '"]' => ['value' => 'custom'],
        ],
        'required' => [
          'input[name="' . $name . '"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $element['link_display']['more']['more_title_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override the button label. Defaults to "See all" or the referenced entity label.'),
      '#default_value' => isset($configuration['more']['title_override']) && !is_null($configuration['more']['title_override']),
      '#states' => [
        'visible' => [
          'input[name="' . $name . '"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $title_override_name = $first_parent . '[' . implode('][', array_merge($parents, [
      'more',
      'more_title_override',
    ])) . ']';
    $element['link_display']['more']['more_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $configuration['more']['title_override'] ?? '',
      '#element_validate' => [[get_class($this), 'validateMoreLinkOverride']],
      '#states' => [
        'visible' => [
          'input[name="' . $name . '"]' => ['value' => 'custom'],
          'input[name="' . $title_override_name . '"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="' . $title_override_name . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Builds the no results behaviour plugin form elements.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param int $delta
   *   The item delta.
   * @param array $element
   *   The form element.
   * @param array $form
   *   The entire form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildNoResultsBehaviourElements(FieldItemListInterface $items, int $delta, array &$element, array &$form, FormStateInterface $form_state): void {
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'no_results_behaviour',
    ]);

    $element['no_results_behaviour'] = [
      '#type' => 'details',
      '#title' => $this->t('No results behaviour'),
      '#open' => TRUE,
    ];

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->getBuildInfo()['callback_object']->getEntity();
    $plugin_id = NestedArray::getValue($form_state->getStorage(), [
      'plugin_select',
      'no_results_behaviour',
    ]);
    if (!$plugin_id) {
      $plugin_id = $this->getConfigurationPluginId($link_list, 'no_results_behaviour');
    }

    $no_results_plugin_options = $this->noResultsBehaviourPluginManager->getPluginsAsOptions();

    // If we don't have a plugin ID and there is only one available option,
    // use that as the default.
    if (!$plugin_id && count($no_results_plugin_options) === 1) {
      $plugin_id = key($no_results_plugin_options);
    }

    if ($no_results_plugin_options) {
      $element['no_results_behaviour']['plugin'] = [
        '#type' => 'select',
        '#description' => $this->t('What should happen if there are no results in the list?'),
        '#title' => $this->t('No results behaviour'),
        '#empty_option' => $this->t('None'),
        '#empty_value' => '',
        '#required' => TRUE,
        '#options' => $no_results_plugin_options,
        '#ajax' => [
          'callback' => [$this, 'pluginConfigurationAjaxCallback'],
          'wrapper' => $element['#attributes']['id'],
        ],
        '#submit' => [
          [get_class($this), 'selectPlugin'],
        ],
        '#default_value' => $plugin_id,
        '#executes_submit_callback' => TRUE,
        '#plugin_select' => 'no_results_behaviour',
        '#limit_validation_errors' => [
          array_merge($parents, ['plugin']),
        ],
        '#access' => !empty($no_results_plugin_options),
      ];

      // A wrapper that the Ajax callback will replace.
      $element['no_results_behaviour']['plugin_configuration_wrapper'] = [
        '#type' => 'container',
        '#weight' => 10,
        '#tree' => TRUE,
      ];
    }
    else {
      $element['no_results_behaviour']['no_plugin'] = [
        '#markup' => $this->t('There are no plugins available.'),
      ];
    }

    if ($plugin_id) {
      $existing_config = $this->getConfigurationPluginConfiguration($link_list, 'no_results_behaviour');
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->noResultsBehaviourPluginManager->createInstance($plugin_id, $existing_config);

      $element['no_results_behaviour']['plugin_configuration_wrapper'][$plugin_id] = [
        '#process' => [[get_class($this), 'processPluginConfiguration']],
        '#plugin' => $plugin,
      ];

      if (!empty($element['#translatable_parents'])) {
        // If we are translating the entity and we have elements that we are
        // translating, add a process to the plugin form to handle them.
        $process = [get_class($this), 'processUntranslatableFields'];
        $element['no_results_behaviour']['plugin_configuration_wrapper'][$plugin_id]['#process'][] = $process;
        $element['no_results_behaviour']['plugin_configuration_wrapper'][$plugin_id]['#translatable_parents'] = $element['#translatable_parents'];
      }
    }
  }

  /**
   * Submit callback for storing the selected plugin ID.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function selectPlugin(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    NestedArray::setValue($form_state->getStorage(), [
      'plugin_select',
      $triggering_element['#plugin_select'],
    ], $triggering_element['#value']);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $field_name = $items->getName();
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $form_state->getBuildInfo()['callback_object']->getEntity();
    $widget_state = self::getWidgetState($form['#parents'], $field_name, $form_state);

    foreach ($items as $delta => $value) {
      $configuration = [];
      $element = NestedArray::getValue($form, array_merge($widget_state['array_parents'], [$delta]));
      $configuration['display'] = $this->extractPluginConfiguration('link_display', $element, $form_state);
      $configuration['no_results_behaviour'] = $this->extractPluginConfiguration('no_results_behaviour', $element, $form_state);

      if (isset($element['link_source'])) {
        $configuration['source'] = $this->extractPluginConfiguration('link_source', $element, $form_state);
      }

      $this->applyGeneralListConfiguration($configuration, $element, $form_state);
      $this->linkListConfigurationManager->setConfiguration($configuration, $items->get($delta));
    }
  }

  /**
   * Extracts plugin configuration values.
   *
   * It instantiates the selected plugin, calls its submit method and returns
   * the configuration values for this plugin type.
   *
   * @param string $plugin_type
   *   The plugin type: link_source or link_display.
   * @param array $element
   *   The single widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The configuration for the plugin type.
   */
  protected function extractPluginConfiguration(string $plugin_type, array $element, FormStateInterface $form_state): array {
    $plugin_managers = [
      'link_source' => $this->linkSourcePluginManager,
      'link_display' => $this->linkDisplayPluginManager,
      'no_results_behaviour' => $this->noResultsBehaviourPluginManager,
    ];

    $configuration = [];

    $plugin_id = $form_state->getValue(array_merge($element['#parents'], [
      $plugin_type,
      'plugin',
    ]));
    if ($plugin_id) {
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $plugin_managers[$plugin_type]->createInstance($plugin_id);

      if (isset($element[$plugin_type]['plugin_configuration_wrapper'][$plugin_id])) {
        $subform_state = SubformState::createForSubform($element[$plugin_type]['plugin_configuration_wrapper'][$plugin_id], $form_state->getCompleteForm(), $form_state);
        $plugin->submitConfigurationForm($element[$plugin_type]['plugin_configuration_wrapper'][$plugin_id], $subform_state);
      }

      // Add the link display plugin configuration.
      $configuration['plugin'] = $plugin_id;
      $configuration['plugin_configuration'] = $plugin->getConfiguration();
    }

    return $configuration;
  }

  /**
   * Applies the general list configuration to the overall config values.
   *
   * @param array $configuration
   *   The list configuration.
   * @param array $element
   *   The single widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function applyGeneralListConfiguration(array &$configuration, array $element, FormStateInterface $form_state): void {
    $parents = array_merge($element['#parents'], ['link_display']);

    // Add the rest of the list configuration.
    $configuration['size'] = (int) $form_state->getValue(array_merge($parents, ['size']));
    if ($configuration['size'] === 0) {
      // If we show all items, we clear any other configuration.
      $configuration['more'] = [
        'button' => 'no',
      ];

      return;
    }

    $more = $form_state->getValue(array_merge($parents, ['more']));
    if ($more['button'] === 'no') {
      // If we don't show all items but we don't want a More button, we clear
      // any other configuration.
      $configuration['more'] = [
        'button' => 'no',
      ];

      return;
    }

    $configuration['more'] = [
      'button' => $more['button'],
    ];

    $configuration['more']['title_override'] = (bool) $more['more_title_override'] ? $more['more_title'] : NULL;

    // Get the target for the More button.
    $target = $more['more_target'];
    $id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($target);
    if (is_numeric($id)) {
      // If we  get an ID, it means we are dealing with a URL.
      $configuration['more']['target'] = [
        'type' => 'entity',
        'entity_type' => 'node',
        'entity_id' => $id,
      ];

      return;
    }

    // Otherwise it's a custom URL.
    $configuration['more']['target'] = [
      'type' => 'custom',
      'url' => $target,
    ];
  }

  /**
   * For processor to build the plugin configuration form.
   *
   * @param array $element
   *   The element onto which to build the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The full form state.
   *
   * @return array
   *   The processed form.
   */
  public static function processPluginConfiguration(array &$element, FormStateInterface $form_state): array {
    /** @var \Drupal\oe_link_lists\LinkSourceInterface $plugin */
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    return $plugin->buildConfigurationForm($element, $subform_state);
  }

  /**
   * The Ajax callback for configuring the plugin.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function pluginConfigurationAjaxCallback(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
  }

  /**
   * Validates the target element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public static function validateMoreTarget(array $element, FormStateInterface $form_state): void {
    $uri = trim($element['#value']);

    $button_parents = array_merge(
      array_slice($element['#parents'], 0, -1),
      ['button']
    );
    $button = $form_state->getValue($button_parents);
    if ($uri === '') {
      if ($button === 'custom') {
        $form_state->setError($element, t('The target is required if you want to override the "See all" button.'));
      }
      // No other validation is needed if the uri is empty.
      return;
    }

    // @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUserEnteredStringAsUri()
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($uri);
    if ($entity_id !== NULL) {
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance([
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ]);
      if (!$handler->validateReferenceableEntities([$entity_id])) {
        $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', [
          '%type' => $element['#target_type'],
          '%id' => $entity_id,
        ]));
      }

      // Either an error or a valid entity is present. Exit early.
      return;
    }

    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }

    // @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget::validateUriElement()
    if (
      parse_url($uri, PHP_URL_SCHEME) === 'internal' &&
      !in_array($element['#value'][0], ['/', '?', '#'], TRUE) &&
      substr($element['#value'], 0, 7) !== '<front>'
    ) {
      $form_state->setError($element, t('The specified target is invalid. Manually entered paths should start with one of the following characters: / ? #'));
    }

    try {
      $url = Url::fromUri($uri);
    }
    catch (\InvalidArgumentException $exception) {
      // Mark the url as invalid.
      $url = FALSE;
    }
    if ($url === FALSE || ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), UrlHelper::getAllowedProtocols()))) {
      $form_state->setError($element, t('The path %uri is invalid.', ['%uri' => $uri]));
    }
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
      ['more_title_override']
    );
    $more_title_override = $form_state->getValue($override_parents);
    if ((bool) $more_title_override) {
      $form_state->setError($element, t('The button label is required if you want to override the "See all" button title.'));
    }
  }

  /**
   * Returns the configured plugin ID.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   * @param string $type
   *   The plugin type.
   *
   * @return null|string
   *   The plugin ID.
   */
  protected function getConfigurationPluginId(LinkListInterface $link_list, string $type): ?string {
    $configuration = $link_list->getConfiguration();
    return $configuration[$type]['plugin'] ?? NULL;
  }

  /**
   * Returns the configured plugin configuration.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   * @param string $type
   *   The plugin type.
   *
   * @return array
   *   The plugin configuration.
   */
  protected function getConfigurationPluginConfiguration(LinkListInterface $link_list, string $type): array {
    $configuration = $link_list->getConfiguration();
    return $configuration[$type]['plugin_configuration'] ?? [];
  }

}
