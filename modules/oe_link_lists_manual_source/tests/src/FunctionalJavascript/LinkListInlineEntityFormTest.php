<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_manual_source\FunctionalJavascript;

/**
 * Tests link lists can be created inside a IEF.
 */
class LinkListInlineEntityFormTest extends ManualLinkListTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_link_lists_ief_test',
  ];

  /**
   * Default user permissions.
   *
   * @var array
   */
  protected $userPermissions = [
    'bypass node access',
    'create manual link list',
    'edit manual link list',
    'create internal link list link',
    'create external link list link',
    'edit external link list link',
    'edit internal link list link',
    'administer entity_test content',
  ];

  /**
   * Tests the link list inside a IEF.
   */
  public function testInInlineEntityForm(): void {
    $web_user = $this->drupalCreateUser($this->userPermissions);
    $this->drupalLogin($web_user);

    $this->drupalGet('/node/add/ief_page');
    // The node title.
    $this->getSession()->getPage()->fillField('title[0][value]', 'Node title');
    $this->getSession()->getPage()->pressButton('ief-field_test_entity_reference-form-add');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Add new link list');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Administrative title', 'Link list admin title');
    // The link list title.
    $this->getSession()->getPage()->fillField('field_test_entity_reference[form][0][field_link_list][form][0][title][0][value]', 'Actual title');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create a link.
    $this->getSession()->getPage()->pressButton('Add new Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertNotNull($links_wrapper);
    $links_wrapper->fillField('URL', 'http://example.com');
    $links_wrapper->fillField('field_test_entity_reference[form][0][field_link_list][form][0][links][form][0][title][0][value]', 'The link title.');
    $links_wrapper->fillField('Teaser', 'The link teaser');
    $this->getSession()->getPage()->pressButton('Create Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('External link to: The link title.');
    $this->assertSession()->linkByHrefExists('http://example.com');

    // Save the link list.
    $this->getSession()->getPage()->pressButton('Create link list');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Save the node.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('IEF Page Node title has been created.');

    // Edit the node.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Link list admin title');

    // Edit the link list and assert we see the link title there.
    $this->getSession()->getPage()->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('External link to: The link title.');
    $this->assertSession()->linkByHrefExists('http://example.com');

    // Make sure that we don't lose the revision id on pressing the Cancel
    // button and saving the node entity.
    $this->getSession()->getPage()->pressButton('ief-edit-cancel-field_test_entity_reference-form-0-field_link_list-form-0');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('IEF Page Node title has been updated.');
  }

  /**
   * Tests that the sort alphabetically option is saved correctly in IEF.
   */
  public function testIefSortAlphabetically(): void {
    $web_user = $this->drupalCreateUser($this->userPermissions);
    $this->drupalLogin($web_user);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $link_list_storage */
    $link_list_storage = \Drupal::service('entity_type.manager')->getStorage('link_list');

    $this->drupalGet('/node/add/ief_page');
    $this->getSession()->getPage()->fillField('title[0][value]', 'Node title');
    $this->getSession()->getPage()->pressButton('ief-field_test_entity_reference-form-add');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Add new link list');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Administrative title', 'Link list admin title');
    $this->getSession()->getPage()->fillField('field_test_entity_reference[form][0][field_link_list][form][0][title][0][value]', 'Link list title');

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert the checkbox is present and unchecked by default.
    $this->assertSession()->fieldExists('Sort links alphabetically');
    $this->assertSession()->checkboxNotChecked('Sort links alphabetically');

    // Check the checkbox and save the link list.
    $this->getSession()->getPage()->checkField('Sort links alphabetically');
    $this->getSession()->getPage()->pressButton('Create link list');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Save the node.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('IEF Page Node title has been created.');

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertEquals('manual_links', $configuration['source']['plugin']);
    $this->assertTrue($configuration['source']['plugin_configuration']['sort_alphabetical']);

    // Edit the node (and link list) and verify the checkbox is checked.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->checkboxChecked('Sort links alphabetically');

    // Uncheck and save the link list, then the node.
    $this->getSession()->getPage()->uncheckField('Sort links alphabetically');
    $this->getSession()->getPage()->pressButton('Update link list');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('IEF Page Node title has been updated.');

    $link_list_storage->resetCache();
    $link_list = $link_list_storage->load(1);
    $configuration = $link_list->getConfiguration();
    $this->assertFalse($configuration['source']['plugin_configuration']['sort_alphabetical']);
  }

}
