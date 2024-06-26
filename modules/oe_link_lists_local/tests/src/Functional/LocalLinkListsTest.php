<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_local\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the local link lists.
 */
class LocalLinkListsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'oe_link_lists_local',
  ];

  /**
   * A user that can edit content types.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    $this->adminUser = $this->drupalCreateUser([], NULL, TRUE);
  }

  /**
   * Tests the field config UI form that marks the field as local.
   */
  public function testFieldConfigUi(): void {
    $this->drupalLogin($this->adminUser);
    $type_path = 'admin/structure/types/manage/page/fields/add-field';
    $this->drupalGet($type_path);

    $this->getSession()->getPage()->selectFieldOption('Reference', 'reference');

    // @todo Remove when support for 10.2.x is dropped.
    if (version_compare(\Drupal::VERSION, '10.3', '>')) {
      $this->getSession()->getPage()->pressButton('Continue');
    }
    else {
      $this->getSession()->getPage()->pressButton('Change field group');
    }

    $this->getSession()->getPage()->selectFieldOption('Other', 'entity_reference');
    $this->getSession()->getPage()->fillField('Label', 'Link list');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'link_list');
    $this->getSession()->getPage()->pressButton('Continue');
    $this->getSession()->getPage()->selectFieldOption('Type of item to reference', 'link_list');
    $this->getSession()->getPage()->pressButton('Update settings');
    $this->getSession()->getPage()->pressButton('Save settings');

    $this->assertSession()->fieldExists('Local field');
    $this->assertSession()->checkboxNotChecked('Local field');
    $this->getSession()->getPage()->checkField('Dynamic');

    // Save the field without marking it as local.
    $this->getSession()->getPage()->pressButton('Save settings');
    $this->assertSession()->pageTextContains('Saved Link list configuration.');

    // Reload the form and assert it is correct.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_link_list');

    $this->assertSession()->checkboxNotChecked('Local field');
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = FieldConfig::load('node.page.field_link_list');
    $this->assertFalse($field_config->getThirdPartySetting('oe_link_lists_local', 'local', FALSE));

    // Mark the field as local.
    $this->getSession()->getPage()->checkField('Local field');
    $this->getSession()->getPage()->pressButton('Save settings');
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_link_list');
    $this->assertSession()->checkboxChecked('Local field');
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = FieldConfig::load('node.page.field_link_list');
    $this->assertTrue($field_config->getThirdPartySetting('oe_link_lists_local', 'local', FALSE));

    // Assert we don't have the checkbox available on other field types.
    $this->drupalGet($type_path);

    $this->getSession()->getPage()->selectFieldOption('Reference', 'plain_text');

    // @todo Remove when support for 10.2.x is dropped.
    if (version_compare(\Drupal::VERSION, '10.3', '>')) {
      $this->getSession()->getPage()->pressButton('Continue');
    }
    else {
      $this->getSession()->getPage()->pressButton('Change field group');
    }

    $this->getSession()->getPage()->selectFieldOption('Text (plain)', 'string');
    $this->getSession()->getPage()->fillField('Label', 'String field');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'field_text');
    $this->getSession()->getPage()->pressButton('Continue');
    $this->assertSession()->fieldNotExists('Local field');
    $this->getSession()->getPage()->pressButton('Save settings');
  }

}
