<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists_internal_source\FunctionalJavascript;

/**
 * Tests that the internal source settings form.
 */
class LinkListsInternalSourceSettingsTest extends InternalLinkSourceTestBase {

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
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test the internal source settings form.
   */
  public function testInternalSourceSettingsForm(): void {
    $this->drupalGet('admin/config/system/link-lists-internal-source-settings');
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
    $page->checkField('News');

    $page->pressButton('Save configuration');

    $page->hasCheckedField('Content');
    $page->hasUncheckedField('Page');
    $page->hasCheckedField('News');

    $page->hasUncheckedField('Dynamic');
    $page->hasUncheckedField('Link list');

    $page->uncheckField('Content');
    $page->checkField('Link list');
    $page->checkField('Dynamic');

    $page->pressButton('Save configuration');

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
