<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_manual_source\Entity\Bundle;

use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;
use Drupal\entity_reference_revisions\EntityNeedsSaveTrait;
use Drupal\oe_link_lists\Entity\LinkList as OriginalLinkList;

/**
 * Defines the LinkList entity.
 */
class LinkList extends OriginalLinkList implements EntityNeedsSaveInterface {

  use EntityNeedsSaveTrait;

}
