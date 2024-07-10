<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for link_display plugins.
 */
abstract class LinkDisplayPluginBase extends PluginBase implements LinkDisplayInterface {

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
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title' => NULL,
      'more' => [],
    ];
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
   * {@inheritdoc}
   */
  public function preSave(ContentEntityInterface $entity): void {
    // Empty in many cases.
  }

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $build = [];
    // The more link.
    $more_link = $this->configuration['more'];
    if ($more_link instanceof Link) {
      $build['more'] = $more_link->toRenderable();
      $build['more']['#access'] = $more_link->getUrl()->access();
      $build['more']['#weight'] = 99;
    }
    return $build;
  }

}
