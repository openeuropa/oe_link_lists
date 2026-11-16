<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\Plugin\Field\FieldType;

use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

/**
 * Replaces the 'entity_reference_revisions' field item class.
 *
 * This is a workaround for a problem in entity_reference_revision
 * https://www.drupal.org/project/entity_reference_revisions/issues/3206499
 * replacing the need to patch the class.
 *
 * @see https://www.drupal.org/project/entity_reference_revisions/issues/3206499
 * @see oe_link_lists_field_info_alter()
 */
class EntityReferenceRevisionsItemFix extends EntityReferenceRevisionsItem {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = parent::getValue();
    if ($this->isEntityLoaded() && (
        ($this->entity instanceof EntityNeedsSaveInterface && $this->entity->needsSave())
        || empty($values['target_revision_id'])
      )) {
      $values['entity'] = $this->entity;
    }
    return $values;
  }

}
