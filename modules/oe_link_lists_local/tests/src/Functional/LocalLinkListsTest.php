<?php

namespace Drupal\Tests\oe_link_lists_local\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;

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
    'oe_link_lists_local_test',
    'oe_link_lists_internal_source',
    'views',
    'entity_reference_revisions',
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

  /**
   * Tests that views don't show the local link lists either.
   */
  public function testViewsQueryAlter(): void {
    // Create 3 link lists: two normal ones (one with 0 value in the local
    // field, and the other with NULL) and a local one.
    $configuration = [
      'source' => [
        'plugin' => 'internal',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'page',
        ],
      ],
      'display' => [
        'plugin' => 'title',
      ],
    ];

    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'The first visible link list',
      'administrative_title' => 'The first visible link list',
    ]);
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'The second visible link list',
      'administrative_title' => 'The second visible link list',
    ]);
    $link_list->setConfiguration($configuration);
    $link_list->set('local', NULL);
    $link_list->save();

    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'The local link list',
      'administrative_title' => 'The local link list',
    ]);
    $link_list->setConfiguration($configuration);
    $link_list->set('local', TRUE);
    $link_list->save();

    $view = Views::getView('link_lists');
    $view->setDisplay();
    $view->preExecute();
    $view->execute();
    $results = $view->result;
    $this->assertCount(2, $results);
    $titles = [];
    foreach ($results as $row) {
      $entity = $row->_entity;
      $titles[] = $entity->label();
    }

    $this->assertEquals([
      'The first visible link list',
      'The second visible link list',
    ], $titles);
  }

}
