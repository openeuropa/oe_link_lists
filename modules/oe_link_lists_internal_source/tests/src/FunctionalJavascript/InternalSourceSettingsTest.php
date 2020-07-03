<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_internal_source\FunctionalJavascript;

use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests the internal source settings form.
 */
class InternalSourceSettingsTest extends InternalLinkSourceTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $this->drupalCreateContentType([
      'type' => 'news',
      'name' => 'News',
    ]);

    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'configure link lists internal source',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests the internal source entity type/bundle restrictions.
   */
  public function testInternalSourceRestrictions(): void {
    $this->drupalGet('admin/config/link-lists/internal-source-settings');
    $page = $this->getSession()->getPage();

    $page->checkField('Link list');
    $this->assertTrue($page->findField('allowed_bundles[link_list][bundles][dynamic]')->isVisible(), 'Dynamic bundle of Link List is visible.');
    $this->assertFalse($page->findField('allowed_bundles[node][bundles][page]')->isVisible(), 'Page content type checkbox is invisible.');
    $this->assertFalse($page->findField('allowed_bundles[node][bundles][news]')->isVisible(), 'News content type checkbox is invisible.');

    $page->checkField('Content');
    $page->uncheckField('Link list');
    $this->assertFalse($page->findField('allowed_bundles[link_list][bundles][dynamic]')->isVisible(), 'Dynamic bundle of Link List is invisible.');
    $this->assertTrue($page->findField('allowed_bundles[node][bundles][page]')->isVisible(), 'Page content type checkbox is visible.');
    $this->assertTrue($page->findField('allowed_bundles[node][bundles][news]')->isVisible(), 'News content type checkbox is visible.');

    // Assert the validation.
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('Please select at least 1 bundle for Content. Or select all of them if you would like all to be included.');

    $page->checkField('News');
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertConfigSchemaByName('oe_link_lists_internal_source.settings');

    $page->hasCheckedField('Content');
    $page->hasUncheckedField('Page');
    $page->hasCheckedField('News');

    $page->hasUncheckedField('Dynamic');
    $page->hasUncheckedField('Link list');

    $page->uncheckField('Content');
    $page->checkField('Link list');
    $page->checkField('Dynamic');

    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertConfigSchemaByName('oe_link_lists_internal_source.settings');

    $page->hasCheckedField('Link list');
    $page->hasUncheckedField('Content');
    $this->assertTrue($page->findField('allowed_bundles[link_list][bundles][dynamic]')->isVisible(), 'Dynamic bundle of Link List is visible.');
    $this->assertFalse($page->findField('allowed_bundles[node][bundles][page]')->isVisible(), 'Page content type checkbox is invisible.');
    $this->assertFalse($page->findField('allowed_bundles[node][bundles][news]')->isVisible(), 'News content type checkbox is invisible.');
    $page->hasCheckedField('Dynamic');
    $page->hasUncheckedField('Page');
    $page->hasUncheckedField('News');
  }

}
