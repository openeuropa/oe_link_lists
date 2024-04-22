<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "test_example_source",
 *   label = @Translation("Example source"),
 *   description = @Translation("A source that provides a few example links."),
 *   bundles = { "dynamic" }
 * )
 */
class ExampleTestSource extends LinkSourcePluginBase {

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
