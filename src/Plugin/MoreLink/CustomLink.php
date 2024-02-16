<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\MoreLink;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\Entity\LinkListInterface;
use Drupal\oe_link_lists\MoreLinkPluginBase;
use Drupal\oe_link_lists\TranslatableLinkListPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows the configuring of a custom link.
 *
 * @MoreLink(
 *   id = "custom_link",
 *   label = @Translation("Custom link"),
 *   description = @Translation("Custom link")
 * )
 */
class CustomLink extends MoreLinkPluginBase implements ContainerFactoryPluginInterface, TranslatableLinkListPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfoManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, ElementInfoManagerInterface $elementInfoManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->elementInfoManager = $elementInfoManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.element_info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLink(LinkListInterface $link_list, CacheableMetadata $cache): ?Link {
    $configuration = $this->configuration;
    $overridden_title = FALSE;
    $title = $this->t('See all');

    if (isset($configuration['title_override']) && mb_strlen($configuration['title_override']) > 0) {
      $overridden_title = TRUE;
      $title = $configuration['title_override'];
    }

    // If we don't have a target, we cannot do anything.
    if (!isset($configuration['target'])) {
      return NULL;
    }

    if ($configuration['target']['type'] === 'custom') {
      return $this->buildCustomUrlLink($title, $configuration);
    }

    if ($configuration['target']['type'] === 'entity') {
      return $this->buildEntityLink($title, $configuration, $overridden_title, $cache);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'target' => [],
      'title_override' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $default_target = '';
    $target_type = $configuration['target']['type'] ?? NULL;
    if ($target_type === 'entity') {
      if ($entity = $this->entityTypeManager->getStorage($configuration['target']['entity_type'])->load($configuration['target']['entity_id'])) {
        $default_target = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    if ($target_type === 'custom') {
      $default_target = $configuration['target']['url'];
    }

    // This element behaves like an entity autocomplete form element but has
    // extra custom validation to allow any routes to be specified.
    $form['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target'),
      '#description' => $this->t('This can be an external link or you can autocomplete to find internal content.'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#autocreate' => FALSE,
      '#process' => $this->elementInfoManager->getInfoProperty('entity_autocomplete', '#process'),
      '#default_value' => $default_target,
      '#element_validate' => [[get_class($this), 'validateMoreTarget']],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $this->buildTitleOverrideForm($form, $configuration);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['target'] = $this->processSubmittedTarget($values['target']);
    $override = (bool) $values['override'];
    if ($override && $values['title_override'] !== "") {
      $this->configuration['title_override'] = $values['title_override'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableParents(): array {
    return [
      [
        'target',
      ],
      [
        'title_override',
      ],
    ];
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
    if ($uri == "") {
      $form_state->setError($element, t('The path %uri is invalid.', ['%uri' => $uri]));
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
   * Processes the value of the submitted target.
   *
   * Since a target can be a custom URL or a reference to an entity, we need to
   * break it down and store it accordingly.
   *
   * @param string $target
   *   The raw submitted target value.
   *
   * @return array
   *   The configuration value.
   */
  protected function processSubmittedTarget(string $target): array {
    $id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($target);
    if (is_numeric($id)) {
      // If we  get an ID, it means we are dealing with a entity.
      return [
        'type' => 'entity',
        'entity_type' => 'node',
        'entity_id' => $id,
      ];
    }

    // Otherwise it's a custom URL.
    return [
      'type' => 'custom',
      'url' => $target,
    ];
  }

  /**
   * Builds the link to a custom non-Entity location.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $title
   *   The link title.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\Core\Link|null
   *   The link.
   */
  protected function buildCustomUrlLink($title, array $configuration): ?Link {
    $has_scheme = parse_url($configuration['target']['url'], PHP_URL_SCHEME) !== NULL;
    try {
      $url = $has_scheme ? Url::fromUri($configuration['target']['url']) : Url::fromUserInput($configuration['target']['url']);
    }
    catch (\InvalidArgumentException $exception) {
      if ($configuration['target']['url'] !== '<front>') {
        return NULL;
      }

      $url = Url::fromRoute('<front>');
    }

    return Link::fromTextAndUrl($title, $url);
  }

  /**
   * Builds the link to an Entity.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $title
   *   The link title.
   * @param array $configuration
   *   The plugin configuration.
   * @param bool $overridden_title
   *   Whether the title was overridden.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheable metadata.
   *
   * @return \Drupal\Core\Link|null
   *   The link.
   */
  protected function buildEntityLink($title, array $configuration, bool $overridden_title, CacheableMetadata $cache): ?Link {
    $url = Url::fromUri("entity:{$configuration['target']['entity_type']}/{$configuration['target']['entity_id']}");

    if (!$overridden_title) {
      $entity = $this->entityTypeManager->getStorage($configuration['target']['entity_type'])->load($configuration['target']['entity_id']);
      if (!$entity instanceof ContentEntityInterface) {
        return NULL;
      }
      $cache->addCacheableDependency($entity);
      $title = $entity->label();
    }

    return Link::fromTextAndUrl($title, $url);
  }

}
