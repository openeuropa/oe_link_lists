<?php

namespace Drupal\oe_link_lists\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the LinkList type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "link_list_type",
 *   label = @Translation("Link List type"),
 *   handlers = {
 *     "access" = "Drupal\oe_link_lists\LinkListTypeAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\oe_link_lists\Form\LinkListTypeForm",
 *       "edit" = "Drupal\oe_link_lists\Form\LinkListTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\oe_link_lists\LinkListTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer link list types",
 *   bundle_of = "link_list",
 *   config_prefix = "link_list_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/link_list_types/add",
 *     "edit-form" = "/admin/structure/link_list_types/manage/{link_list_type}",
 *     "delete-form" = "/admin/structure/link_list_types/manage/{link_list_type}/delete",
 *     "collection" = "/admin/structure/link_list_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "configurable_link_source_plugins",
 *     "default_link_source",
 *   }
 * )
 */
class LinkListType extends ConfigEntityBundleBase {

  /**
   * The machine name of this linklist type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the linklist type.
   *
   * @var string
   */
  protected $label;

  /**
   * Whether the bundle should show the link source selection on the form.
   *
   * @var bool
   */
  protected $configurable_link_source_plugins;

  /**
   * The link source this bundle works with automatically.
   *
   * This option is used by the bundles which use a non-selectable link source
   * plugin that works only with this bundle.
   *
   * @var string
   */
  protected $default_link_source;

  /**
   * Returns true if the bundle shows the link source selection on the form.
   *
   * @return bool
   *   Whether it's configurable.
   */
  public function isLinkSourceConfigurable(): bool {
    return (bool) $this->configurable_link_source_plugins;
  }

  /**
   * Returns the default links source plugin this bundle works with.
   *
   * @return string|null
   *   The link source plugin.
   */
  public function getDefaultLinkSource(): ?string {
    return $this->default_link_source;
  }

}
