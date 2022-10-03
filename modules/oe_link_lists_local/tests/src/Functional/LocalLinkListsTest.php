<?php

namespace Drupal\Tests\oe_link_lists_local\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the local link lists.
 */
class LocalLinkListsTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

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

    $initial_edit = [
      'new_storage_type' => 'entity_reference',
      'label' => 'Link list',
      'field_name' => 'link_list',
    ];

    $this->submitForm($initial_edit, $this->t('Save and continue'));

    $this->getSession()->getPage()->selectFieldOption('Type of item to reference', 'link_list');
    $this->getSession()->getPage()->pressButton('Save field settings');

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

    $initial_edit = [
      'new_storage_type' => 'string',
      'label' => 'String field',
      'field_name' => 'field_text',
    ];
    $this->submitForm($initial_edit, $this->t('Save and continue'));
    $this->getSession()->getPage()->pressButton('Save field settings');
    $this->assertSession()->pageTextContains('Updated field String field field settings.');
    $this->assertSession()->fieldNotExists('Local field');
  }

}
