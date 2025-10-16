<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_group\Functional;

use Drupal\Tests\group\Functional\EntityOperationsTest as GroupEntityOperationsTest;

/**
 * Tests that entity operations (do not) show up on the group overview.
 *
 * @see oe_link_lists_group_entity_operation()
 */
class EntityOperationsTest extends GroupEntityOperationsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['oe_link_lists_group'];

  /**
   * {@inheritdoc}
   *
   * @dataProvider provideLinkListsEntityOperationScenarios
   */
  // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
  public function testEntityOperations($visible, $invisible, $permissions = [], $modules = []) {
    parent::testEntityOperations($visible, $invisible, $permissions, $modules);
  }

  /**
   * Data provider for TestEntityOperations().
   */
  public static function provideLinkListsEntityOperationScenarios(): array {
    $scenarios['withoutAccess'] = [
      [],
      ['group/1/link-lists' => 'Link lists'],
    ];

    $scenarios['withAccess'] = [
      [],
      ['group/1/link-lists' => 'Link lists'],
      [
        'view group',
        'access group_link_lists overview',
      ],
    ];

    $scenarios['withAccessAndViews'] = [
      ['group/1/link-lists' => 'Link lists'],
      [],
      [
        'view group',
        'access group_link_lists overview',
      ],
      ['views'],
    ];

    return $scenarios;
  }

}
