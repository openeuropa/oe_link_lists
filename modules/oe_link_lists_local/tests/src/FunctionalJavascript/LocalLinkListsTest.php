<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_local\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\workflows\Entity\Workflow;

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
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Default user permissions.
   *
   * @var array
   */
  protected $userPermissions = [
    'bypass node access',
    'create dynamic link list',
    'edit dynamic link list',
    'use editorial transition create_new_draft',
    'use editorial transition publish',
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

    // Configure the link lists to use the editorial workflow.
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('link_list', 'dynamic');
    $workflow->save();
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

      // Assert we cannot see the moderation state field since it's hidden for
      // local link lists even if link lists are moderated.
      $this->assertSession()->fieldNotExists('Save as');

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
      $ids = $query->execute();
      $this->assertCount(1, $ids);
      $id = reset($ids);
      // We can load directly by ID.
      $link_list = LinkList::load($id);
      // Local link lists, even if there is moderation on the node, get
      // automatically saved as published and with the moderation state that
      // indicates a published status.
      $this->assertTrue($link_list->isPublished());
      $this->assertEquals('published', $link_list->get('moderation_state')->value);

      // Edit the node and remove the link list.
      $this->drupalGet($node->toUrl('edit-form'));
      $this->getSession()->getPage()->pressButton('Remove');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->assertSession()->pageTextContains(sprintf('Are you sure you want to remove this %s?', $info['singular label']));
      $this->getSession()->getPage()->pressButton('Remove');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->getSession()->getPage()->pressButton('Save');
    }

    // Create a non-local link list and assert that moderation is not impacted.
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->fillField('Title', 'Regular');
    $this->getSession()->getPage()->fillField('Administrative title', 'Regular');
    $this->assertSession()->fieldExists('Save as');
    $this->getSession()->getPage()->selectFieldOption('Link source', 'Example source');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Regular Link list.');
    $link_list = $this->getLinkListByTitle('Regular', TRUE);
    $this->assertFalse($link_list->isPublished());
    $this->assertEquals('draft', $link_list->get('moderation_state')->value);

  }

}
