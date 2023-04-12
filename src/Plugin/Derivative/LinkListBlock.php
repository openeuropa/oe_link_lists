<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class for link list blocks.
 */
class LinkListBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LinkListBlock constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface[] $link_lists */
    $link_lists = $this->entityTypeManager->getStorage('link_list')->loadMultiple();
    foreach ($link_lists as $link_list) {
      if ($link_list->getAdministrativeTitle()) {
        $this->derivatives[$link_list->uuid()] = $base_plugin_definition;
        $this->derivatives[$link_list->uuid()]['admin_label'] = $link_list->getAdministrativeTitle();
        $this->derivatives[$link_list->uuid()]['config_dependencies']['config'] = [$link_list->getConfigDependencyName()];
      }
    }

    return $this->derivatives;
  }

}
