<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists_test\Plugin\LinkSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;

/**
 * Plugin implementation of the link_source.
 *
 * @LinkSource(
 *   id = "test_no_bundle_restriction_source",
 *   label = @Translation("Source without bundle restriction"),
 *   description = @Translation("Link source available on any bundle"),
 * )
 */
class NoBundleRestrictionTestSource extends LinkSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLinks(?int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    return new LinkCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
