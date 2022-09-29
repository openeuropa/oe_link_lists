<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_local\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;

/**
 * Tests link lists can be created inside a IEF.
 */
class LocalLinkListsTest extends WebDriverTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'link',
    'oe_link_lists',
    'oe_link_lists_test',
    'oe_link_lists_local_test',
    'entity_reference_revisions',
    'inline_entity_form',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Default user permissions.
   *
   * @var array
   */
  protected $userPermissions = [
    'bypass node access',
    'create dynamic link list',
    'edit dynamic link list',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_config_link_list',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'link_list',
      ],
    ])->save();

    // Create the test field.
    $field_config = FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_config_link_list',
      'bundle' => 'page',
      'settings' => [
        'handler_settings' => [
          'target_bundles' => ['dynamic' => 'dynamic'],
        ],
      ],
    ]);
    $field_config->setThirdPartySetting('oe_link_lists_local', 'local', TRUE);
    $field_config->save();

    $entity_form_display = EntityFormDisplay::load('node.page.default');
    $entity_form_display->setComponent('field_config_link_list', [
      'type' => 'inline_entity_form_complex',
      'weight' => 10,
      'settings' => [
        'revision' => TRUE,
        'allow_new' => TRUE,
        'removed_reference' => 'keep',
        'allow_existing' => FALSE,
        'allow_duplicate' => FALSE,
        'override_labels' => TRUE,
        'label_singular' => 'Field config link list',
        'label_plural' => 'Field config link lists',
      ],
    ]);
    $entity_form_display->save();
  }

  /**
   * Tests the local link list using a IEF.
   */
  public function testLocalLinkList(): void {
    $web_user = $this->drupalCreateUser($this->userPermissions);
    $this->drupalLogin($web_user);

    // We test both with the base field and the field config.
    $test_map = [
      'base field' => [
        'singular label' => 'Base field link list',
        'title form element' => 'link_list[form][0][title][0][value]',
        'link list title' => 'Base field link list title',
        'node title' => 'Base field node title',
      ],
      'field config' => [
        'singular label' => 'Field config link list',
        'title form element' => 'field_config_link_list[form][0][title][0][value]',
        'link list title' => 'Field config link list title',
        'node title' => 'Field config node title',
      ],
    ];

    foreach ($test_map as $info) {
      $this->drupalGet('/node/add/page');
      // The node title.
      $this->getSession()->getPage()->fillField('title[0][value]', $info['node title']);
      $this->getSession()->getPage()->pressButton('Add new ' . $info['singular label']);
      $this->assertSession()->assertWaitOnAjaxRequest();
      // Local link lists don't show an admin title field.
      $this->assertSession()->fieldNotExists('Administrative title');
      // The link list title.
      $this->getSession()->getPage()->fillField($info['title form element'], $info['link list title']);

      // Select and configure the plugins.
      $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
      $this->assertSession()->assertWaitOnAjaxRequest();

      // Save the link list.
      $this->getSession()->getPage()->pressButton('Create ' . $info['singular label']);
      $this->assertSession()->assertWaitOnAjaxRequest();
      // Save the node.
      $this->getSession()->getPage()->pressButton('Save');
      $this->assertSession()->pageTextContains(sprintf('Page %s has been created.', $info['node title']));

      // Edit the node.
      $node = $this->drupalGetNodeByTitle($info['node title']);
      $this->drupalGet($node->toUrl('edit-form'));

      // We see the actual link list title in the table instead of the admin
      // title because it's a local link list.
      $this->assertSession()->elementContains('css', 'td.inline-entity-form-link_list-title', $info['link list title']);

      // Since it is local, no query should be able to find the link list.
      $link_list = $this->getLinkListByTitle($info['link list title'], TRUE);
      $this->assertNull($link_list);
      $query = \Drupal::entityQuery('link_list')
        ->condition('title', $info['link list title'])
        ->accessCheck(FALSE);
      $this->assertEmpty($query->execute());
      $query->addTag('allow_local_link_lists');
      $this->assertCount(1, $query->execute());

      // Edit the node and remove the link list.
      $this->drupalGet($node->toUrl('edit-form'));
      $this->getSession()->getPage()->pressButton('Remove');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->assertSession()->pageTextContains(sprintf('Are you sure you want to remove this %s?', $info['singular label']));
      $this->getSession()->getPage()->pressButton('Remove');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->getPage()->pressButton('Save');
    }
  }

}
