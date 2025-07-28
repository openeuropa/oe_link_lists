<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists_manual_source\FunctionalJavascript;

use Drupal\node\NodeInterface;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface;

/**
 * Tests the Manual link lists allow to add links on the fly.
 *
 * @group oe_link_lists
 */
class ManualLinkListFormTest extends ManualLinkListTestBase {

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
  ];

  /**
   * Tests that we can create link lists with manually defined links.
   *
   * Tests a number of combinations of external and internal links.
   */
  public function testManualLinkList(): void {
    $web_user = $this->drupalCreateUser($this->userPermissions);
    $this->drupalLogin($web_user);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $link_storage */
    $link_storage = \Drupal::service('entity_type.manager')->getStorage('link_list_link');
    /** @var \Drupal\Core\Entity\EntityStorageInterface $link_list_storage */
    $link_list_storage = \Drupal::service('entity_type.manager')->getStorage('link_list');

    // Go to a link list creation page and assert that we can choose the type.
    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test list');
    $this->getSession()->getPage()->fillField('Administrative title', 'List 1');

    // Assert we don't see a Link source selector.
    $this->assertSession()->fieldNotExists('Link Source');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create an external link.
    $this->createInlineExternalLink('http://example.com', 'Test title', 'Test teaser');

    // Save the list and make sure the values are saved correctly.
    $this->getSession()->getPage()->pressButton('Save');
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);
    $this->assertEquals('Test list', $link_list->getTitle());
    $this->assertEquals('List 1', $link_list->getAdministrativeTitle());
    $links = $link_list->get('links')->referencedEntities();
    $this->assertCount(1, $links);
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $link = reset($links);
    $this->assertEquals('http://example.com', $link->get('url')->uri);
    $this->assertEquals('Test title', $link->get('title')->value);
    $this->assertEquals('Test teaser', $link->get('teaser')->value);
    // Check also the plugin configuration values.
    $configuration = $link_list->getConfiguration();
    $link_ids = array_column($link_list->get('links')->getValue(), 'target_id');
    $this->assertEquals('manual_links', $configuration['source']['plugin']);
    $this->assertEquals($link_ids, array_column($configuration['source']['plugin_configuration']['links'], 'entity_id'));
    // Build the links and ensure the data is correctly prepared.
    $links = $this->getLinksFromList($link_list);
    /** @var \Drupal\oe_link_lists\LinkInterface $link */
    $link = reset($links);
    $this->assertInstanceOf(DefaultLink::class, $link);
    $this->assertEquals('http://example.com', $link->getUrl()->toString());
    $this->assertEquals('Test title', $link->getTitle());
    $this->assertEquals('Test teaser', $link->getTeaser()['#text']);
    $this->assertCount(1, $link_list_storage->loadMultiple());
    // Test the getOptions and setOptions work correctly.
    $link->setOptions([
      'foo1' => 'bar1',
      'foo2' => 'bar2',
    ]);
    $this->assertEquals([
      'foo1' => 'bar1',
      'foo2' => 'bar2',
    ], $link->getOptions());

    // Edit the link list and check the values are shown correctly in the form.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('External link to: ' . $link->getTitle());
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[1]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertSession()->fieldValueEquals('URL', 'http://example.com', $links_wrapper);
    $this->assertSession()->fieldValueEquals('Title', 'Test title', $links_wrapper);
    $this->assertSession()->fieldValueEquals('Teaser', 'Test teaser', $links_wrapper);
    $this->getSession()->getPage()->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create an internal link.
    $this->createInlineInternalLink('1');
    $this->getSession()->getPage()->pressButton('Save');

    // Check the values are stored correctly.
    $link_list_storage->resetCache();
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);
    $this->assertCount(2, $link_list->get('links')->getValue());
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $link = $link_list->get('links')->offsetGet(1)->entity;
    $this->assertInstanceOf(NodeInterface::class, $link->get('target')->entity);
    $this->assertTrue($link->get('title')->isEmpty());
    $this->assertTrue($link->get('teaser')->isEmpty());
    // Check also the plugin configuration values.
    $configuration = $link_list->getConfiguration();
    $link_ids = array_column($link_list->get('links')->getValue(), 'target_id');
    $this->assertEquals('manual_links', $configuration['source']['plugin']);
    $this->assertEquals($link_ids, array_column($configuration['source']['plugin_configuration']['links'], 'entity_id'));
    // Build the links and ensure the data is correctly prepared.
    $links = $this->getLinksFromList($link_list);
    /** @var \Drupal\oe_link_lists\EntityAwareLinkInterface $link */
    $link = $links[1];
    $this->assertInstanceOf(DefaultEntityLink::class, $link);
    $this->assertInstanceOf(NodeInterface::class, $link->getEntity());
    $this->assertEquals($link->getEntity()->toUrl(), $link->getUrl());
    $this->assertEquals('Page 1', $link->getTitle());
    $this->assertEquals('Page 1 body', $link->getTeaser()['#text']);
    $link_storage->resetCache();
    $this->assertCount(2, $link_storage->loadMultiple());

    // Edit the link list and check the values are shown correctly in the form.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('External link to: Test title');
    $this->assertSession()->pageTextContains('Internal link to: Page 1');
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[2]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertSession()->fieldValueEquals('Target', 'Page 1 (1)', $links_wrapper);
    $this->assertSession()->checkboxNotChecked('Override target values');
    $this->assertFalse($this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex .field--name-title')->isVisible());
    $this->assertFalse($this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex .field--name-teaser')->isVisible());
    $this->getSession()->getPage()->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Create an internal link with title and teaser override.
    $this->createInlineInternalLink('2', 'Overridden title', 'Overridden teaser');
    $this->getSession()->getPage()->pressButton('Save');

    // Check the values are stored correctly.
    $link_list_storage->resetCache();
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);
    $this->assertCount(3, $link_list->get('links')->getValue());
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $link = $link_list->get('links')->offsetGet(2)->entity;
    $this->assertInstanceOf(NodeInterface::class, $link->get('target')->entity);
    $this->assertEquals('Overridden title', $link->get('title')->value);
    $this->assertEquals('Overridden teaser', $link->get('teaser')->value);
    // Build the links and ensure the data is correctly prepared.
    $links = $this->getLinksFromList($link_list);
    /** @var \Drupal\oe_link_lists\EntityAwareLinkInterface $link */
    $link = $links[2];
    $this->assertInstanceOf(DefaultEntityLink::class, $link);
    $this->assertInstanceOf(NodeInterface::class, $link->getEntity());
    $this->assertEquals($link->getEntity()->toUrl(), $link->getUrl());
    $this->assertEquals('Overridden title', $link->getTitle());
    $this->assertEquals('Overridden teaser', $link->getTeaser()['#text']);
    $link_storage->resetCache();
    $this->assertCount(3, $link_storage->loadMultiple());

    // Uncheck the override and make sure there are no more override values.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[3]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertSession()->fieldValueEquals('Target', 'Page 2 (2)', $links_wrapper);
    $this->assertSession()->checkboxChecked('Override target values', $links_wrapper);
    $links_wrapper->uncheckField('Override target values');
    $this->getSession()->getPage()->pressButton('Update Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $link_list_storage->resetCache();
    $link_list = $link_list_storage->load(1);
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $link = $link_list->get('links')->offsetGet(2)->entity;
    $this->assertTrue($link->get('title')->isEmpty());
    $this->assertTrue($link->get('teaser')->isEmpty());

    // Remove the last link reference and check if the entity is not deleted
    // because the IEF widget is configured to keep the removed entities.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $remove = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Remove"])[3]');
    $remove->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $remove->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $link_list_storage->resetCache();
    $link_list = $link_list_storage->load(1);
    $this->assertEmpty($link_list->get('links')->offsetGet(2));
    $link_storage->resetCache();
    $this->assertInstanceOf(LinkListLinkInterface::class, $link_storage->load($link->id()));
  }

  /**
   * Tests that the manual list can work with other link bundles.
   */
  public function testExtraBundle(): void {
    // Install and enable the internal_route bundle.
    \Drupal::service('module_installer')->install(['oe_link_lists_manual_source_test']);

    $this->userPermissions[] = 'create internal_route link list link';
    $this->userPermissions[] = 'edit internal_route link list link';
    $this->userPermissions[] = 'access link list canonical page';
    $this->userPermissions[] = 'view link list';
    $web_user = $this->drupalCreateUser($this->userPermissions);
    $this->drupalLogin($web_user);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $link_list_storage */
    $link_list_storage = \Drupal::service('entity_type.manager')->getStorage('link_list');

    $this->drupalGet('link_list/add/manual');
    $this->getSession()->getPage()->fillField('Title', 'Test list');
    $this->getSession()->getPage()->fillField('Administrative title', 'List 1');

    // Select and configure the display plugin.
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Links');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and configure the no results behaviour plugin.
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->selectFieldOption('links[actions][bundle]', 'internal_route');
    $this->getSession()->getPage()->pressButton('Add new Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertNotNull($links_wrapper);
    $links_wrapper->fillField('URL', '/node');
    $links_wrapper->fillField('Title', 'Link title');
    $links_wrapper->fillField('Teaser', 'Link teaser');
    $this->getSession()->getPage()->pressButton('Create Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The internal route link was not resolved.
    $this->assertSession()->pageTextContains('Internal Route link: Unresolved');

    // Save the list and make sure the values are saved correctly.
    $this->getSession()->getPage()->pressButton('Save');

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);

    // View the link list and assert that we don't have the link as it was not
    // yet resolved.
    $this->drupalGet($link_list->toUrl());
    $this->assertSession()->linkNotExists('Link title');

    // Instruct the subscriber to resolve the internal route links.
    \Drupal::state()->set('oe_link_lists_manual_source_test_subscriber_resolve', TRUE);

    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = $link_list_storage->load(1);
    $links = $link_list->get('links')->referencedEntities();
    $this->assertCount(1, $links);
    /** @var \Drupal\oe_link_lists_manual_source\Entity\LinkListLinkInterface $link */
    $link = reset($links);
    $this->assertEquals('internal:/node', $link->get('url')->uri);
    $this->assertEquals('Link title', $link->get('title')->value);
    $this->assertEquals('Link teaser', $link->get('teaser')->value);

    // Edit the link list and check the values are shown correctly in the form.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('Internal Route link: Link title');
    $edit = $this->getSession()->getPage()->find('xpath', '(//input[@type="submit" and @value="Edit"])[1]');
    $edit->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $links_wrapper = $this->getSession()->getPage()->find('css', '.field--widget-inline-entity-form-complex');
    $this->assertSession()->fieldValueEquals('URL', '/node', $links_wrapper);
    $this->assertSession()->fieldValueEquals('Title', 'Link title', $links_wrapper);
    $this->assertSession()->fieldValueEquals('Teaser', 'Link teaser', $links_wrapper);
  }

}
