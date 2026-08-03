<?php

declare(strict_types=1);

namespace Drupal\oe_link_lists\EventSubscriber;

use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Default subscriber that resolves event values into a link object.
 */
class DefaultEntityValueResolverSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [EntityValueResolverEvent::NAME => 'resolveEntityValues'];
  }

  /**
   * Resolves the link object from an entity with simple and default values.
   *
   * @param \Drupal\oe_link_lists\Event\EntityValueResolverEvent $event
   *   The event.
   */
  public function resolveEntityValues(EntityValueResolverEvent $event): void {
    $entity = $event->getEntity();
    $title = $entity->label() ?? '';
    $teaser = [
      '#markup' => '',
    ];
    if ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
      $body = $entity->get('body');
      $size = (int) \Drupal::config('text.settings')->get('default_summary_length');
      // The TextSummary service replaces text_summary() in Drupal 11.4; use it
      // when available and fall back to the function on older cores.
      $text_summary_service = 'Drupal\text\TextSummary';
      if (\Drupal::hasService($text_summary_service)) {
        $summary = \Drupal::service($text_summary_service)->generate($body->value, $body->format, $size);
      }
      else {
        // Called via a variable so the fallback isn't flagged as deprecated.
        $text_summary = 'text_summary';
        $summary = $text_summary($body->value, $body->format, $size);
      }
      $teaser = [
        '#type' => 'processed_text',
        '#text' => $summary,
        '#format' => $body->format,
      ];
    }

    try {
      $url = $entity->toUrl();
    }
    catch (\Exception $exception) {
      // This should not happen normally as referenceable entity types have a
      // canonical URL. But in case an entity doesn't, we should not crash
      // the entire thing.
      $url = Url::fromRoute('<front>');
    }
    $link = new DefaultEntityLink($url, $title, $teaser);
    $link->addCacheableDependency($event);
    $link->setEntity($entity);
    $event->setLink($link);
  }

}
