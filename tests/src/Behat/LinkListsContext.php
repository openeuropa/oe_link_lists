<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_link_lists\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Context specific to testing link lists.
 */
class LinkListsContext extends RawDrupalContext {

  /**
   * Selects one of the internal link types.
   *
   * @param string $link_type
   *   The link type.
   *
   * @When I select the :link_type link type
   */
  public function iSelectTheLinkType(string $link_type): void {
    $this->getSession()->getPage()->selectFieldOption('links[actions][bundle]', $link_type);
  }

}
