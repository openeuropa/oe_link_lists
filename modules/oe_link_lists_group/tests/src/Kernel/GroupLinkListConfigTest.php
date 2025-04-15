<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_group\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests that all config provided by this module passes validation.
 */
class GroupLinkListConfigTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity',
    'flexible_permissions',
    'oe_link_lists',
    'oe_link_lists_group',
    'group',
    'node',
    'options',
    'views',
  ];

  /**
   * Tests that the module's config installs properly.
   */
  public function testConfig() {
    $this->installConfig(['oe_link_lists_group']);
  }

}
