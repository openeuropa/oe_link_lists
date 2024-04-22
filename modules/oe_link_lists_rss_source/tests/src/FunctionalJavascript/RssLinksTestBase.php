<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_rss_source\FunctionalJavascript;

use Drupal\aggregator\FeedStorageInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Base class for RSS Links functional tests.
 *
 * @group oe_link_lists
 */
abstract class RssLinksTestBase extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * The link storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $linkStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists',
    'oe_link_lists_rss_source',
    'oe_link_lists_rss_source_test',
    'oe_link_lists_test',
    'oe_multilingual',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'dynamic', TRUE);
    \Drupal::service('router.builder')->rebuild();

    // Do not delete old aggregator items during these tests, since our sample
    // feeds have hardcoded dates in them (which may be expired when this test
    // is run).
    \Drupal::configFactory()->getEditable('aggregator.settings')->set('items.expire', FeedStorageInterface::CLEAR_NEVER)->save();

    $this->placeBlock('local_tasks_block');
  }

}
