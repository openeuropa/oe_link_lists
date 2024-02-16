<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_internal_source\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Base test class for internal link browser tests.
 */
abstract class InternalLinkSourceTestBase extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists_test',
    'oe_link_lists_internal_source',
  ];

  /**
   * Disables the native browser validation for required fields.
   */
  protected function disableNativeBrowserRequiredFieldValidation() {
    $this->getSession()->executeScript("jQuery(':input[required]').prop('required', false);");
  }

}
