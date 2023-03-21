<?php

/**
 * @file
 * Post update functions for OE Link Lists.
 */

declare(strict_types = 1);

use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\oe_link_lists\Entity\LinkListType;

/**
 * Update all the bundles to use the link source plugin selection.
 */
function oe_link_lists_post_update_00001() {
  $link_list_types = LinkListType::loadMultiple();
  foreach ($link_list_types as $id => $link_list_type) {
    if ($id === 'manual') {
      // The manual one is handled in its own submodule.
      continue;
    }

    $link_list_type->set('configurable_link_source_plugins', TRUE);
    $link_list_type->save();
  }
}

/**
 * Update all link lists to set the default no_results_behaviour plugin.
 */
function oe_link_lists_post_update_00002(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Get all the link lists.
    $ids = \Drupal::entityTypeManager()
      ->getStorage('link_list')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return t('No link lists need to be updated.');
    }

    $sandbox['ids'] = $ids;
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
    $sandbox['items_per_batch'] = 10;
  }

  $ids = array_slice($sandbox['ids'], $sandbox['current'], $sandbox['items_per_batch']);
  /** @var \Drupal\oe_link_lists\Entity\LinkListInterface[] $link_lists */
  $link_lists = LinkList::loadMultiple($ids);
  foreach ($link_lists as $link_list) {
    $configuration = $link_list->getConfiguration();
    if (isset($configuration['no_results_behaviour'])) {
      // This should not happen but in case something went wrong.
      $sandbox['current']++;
      continue;
    }
    $configuration['no_results_behaviour'] = [
      'plugin' => 'hide_list',
      'plugin_configuration' => [],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] === 1) {
    return t('A total of @updated link lists have been updated.', ['@updated' => $sandbox['current']]);
  }
}

/**
 * Update all link lists to set the default more_link plugin.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function oe_link_lists_post_update_00003(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Get all the link lists.
    $ids = \Drupal::entityTypeManager()
      ->getStorage('link_list')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return t('No link lists need to be updated.');
    }

    $sandbox['ids'] = $ids;
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
    $sandbox['updated'] = 0;
    $sandbox['items_per_batch'] = 10;
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $sandbox['languages'][] = $language->getId();
    }
  }

  $ids = array_slice($sandbox['ids'], $sandbox['current'], $sandbox['items_per_batch']);
  /** @var \Drupal\oe_link_lists\Entity\LinkListInterface[] $link_lists */
  $link_lists = LinkList::loadMultiple($ids);
  foreach ($link_lists as $link_list) {
    $needs_save = FALSE;
    foreach ($sandbox['languages'] as $language) {
      $translation = $link_list->hasTranslation($language) ? $link_list->getTranslation($language) : NULL;
      if (!$translation) {
        continue;
      }

      // Determine if we are updating the original config or a translation
      // because we need to know later how to handle the config array.
      $original = $translation->language()->getId() === $link_list->getUntranslated()->language()->getId();

      // We cannot use the `getConfiguration()` API method because that will
      // attempt merging of array keys so if one of the translations loses some
      // translatable keys, the API won't be able to merge them anymore. So we
      // need to work using the raw configuration array stored in the entity
      // and ensure that we are updating accordingly below.
      $update = _oe_link_lists_update_configuration_with_default_more_link($translation->get('configuration')->get(0)->getValue(), $original);
      if ($update['needs save']) {
        $needs_save = TRUE;
        $translation->set('configuration', $update['configuration']);
      }
    }

    if ($needs_save) {
      $sandbox['updated']++;
      $link_list->save();
    }

    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] === 1) {
    return t('A total of @updated link lists out of @total have been updated.', [
      '@updated' => $sandbox['updated'],
      '@total' => $sandbox['total'],
    ]);
  }
}

/**
 * Updates a link list configuration with the "more_link" plugin configuration.
 *
 * This method receives the raw config values of either the original language
 * or of the translation (which has less keys). So we need to ensure we are
 * updating the translation keys accordingly and not set values which don't
 * belong in there.
 *
 * @param array $configuration
 *   The configuration array.
 * @param bool $original_language
 *   Whether it's the config of the original language.
 *
 * @return array
 *   An array with the config and whether a save is required.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function _oe_link_lists_update_configuration_with_default_more_link(array $configuration, bool $original_language): array {
  $update = [
    'configuration' => $configuration,
    'needs save' => FALSE,
  ];

  // If it's already set on it for any reason, we don't do anything.
  if (isset($configuration['more_link'])) {
    return $update;
  }

  if (empty($configuration)) {
    // It means we are probably on a translation and there is no configuration
    // translated.
    return $update;
  }

  $more = $configuration['more'] ?? [];
  if ((!$more || !isset($more['button'])) && $original_language) {
    // If we are on the original language and we don't have a "more" button
    // configured, we just default to the empty "more_link" array. This does
    // not happen for the translations.
    $configuration['more_link'] = [];
    $update['needs save'] = TRUE;
    $update['configuration'] = $configuration;
    return $update;
  }

  if (isset($more['button']) && $more['button'] === 'no') {
    // It means there is no "more" link configured. It also means we are on
    // the original because the "button" key never ends up in the translation
    // array. And we just default to the empty "more_link" array.
    $configuration['more_link'] = [];
    unset($configuration['more']);
    $update['needs save'] = TRUE;
    $update['configuration'] = $configuration;
    return $update;
  }

  if ($original_language) {
    $configuration['more_link'] = [
      'plugin' => 'custom_link',
      'plugin_configuration' => [
        'target' => $more['target'],
        'title_override' => $more['title_override'],
      ],
    ];
  }
  else {
    $configuration['more_link'] = [
      'plugin_configuration' => [],
    ];

    if (isset($more['target'])) {
      $configuration['more_link']['plugin_configuration']['target'] = $more['target'];
    }
    if (isset($more['title_override'])) {
      $configuration['more_link']['plugin_configuration']['title_override'] = $more['title_override'];
    }
  }

  unset($configuration['more']);
  $update['configuration'] = $configuration;
  $update['needs save'] = TRUE;
  return $update;
}
