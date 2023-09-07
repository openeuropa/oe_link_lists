<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Entity\Bundle;

use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;
use Drupal\entity_reference_revisions\EntityNeedsSaveTrait;
use Drupal\oe_link_lists\Entity\LinkList;

/**
 * Defines the LinkList entity for Manual bundle.
 */
class ManualLinkList extends LinkList implements EntityNeedsSaveInterface {

  use EntityNeedsSaveTrait;

}
