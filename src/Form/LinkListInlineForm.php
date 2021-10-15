<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Link list inline entity form handler.
 */
class LinkListInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  protected function buildEntity(array $entity_form, ContentEntityInterface $entity, FormStateInterface $form_state) {
    parent::buildEntity($entity_form, $entity, $form_state);
    $configuration = $entity->getConfiguration();
    if (!isset($configuration['source'])) {
      $bundle = $this->entityTypeManager->getStorage('link_list_type')->load($entity->bundle());
      $auto_plugin = $bundle->getDefaultLinkSource();
      if (!$auto_plugin) {
        return;
      }

      $configuration['source'] = [
        'plugin' => $auto_plugin,
        'plugin_configuration' => [],
      ];
      $entity->setConfiguration($configuration);
    }
  }

}
