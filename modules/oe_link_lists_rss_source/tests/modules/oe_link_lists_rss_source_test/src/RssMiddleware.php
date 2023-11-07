<?php

declare(strict_types = 1);

namespace Drupal\oe_link_lists_rss_source_test;

use Drupal\Core\Extension\ExtensionPathResolver;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Mocks the RSS aggregator responses.
 */
class RssMiddleware {

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Constructs a new RssMiddleware.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   */
  public function __construct(ExtensionPathResolver $extensionPathResolver) {
    $this->extensionPathResolver = $extensionPathResolver;
  }

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $url = (string) $request->getUri();
        $filename = NULL;
        $test_module_path = $this->extensionPathResolver->getPath('module', 'aggregator_test');
        \Drupal::state()->resetCache();
        $extra_item = \Drupal::state()->get('oe_link_lists_rss_source_test_extra_atom', FALSE) === TRUE;

        switch ($url) {
          case 'http://www.example.com/atom.xml':
            $filename = $test_module_path . DIRECTORY_SEPARATOR . 'aggregator_test_atom.xml';

            if ($extra_item) {
              // Mock the addition of a new item to the feed.
              $filename = $this->extensionPathResolver->getPath('module', 'oe_link_lists_rss_source_test') . '/fixtures/aggregator_test_atom_extra_item.xml';
            }
            break;

          case 'http://www.example.com/rss.xml':
            $filename = $test_module_path . DIRECTORY_SEPARATOR . 'aggregator_test_rss091.xml';
            if ($extra_item) {
              // Mock the addition of a new item to the feed.
              $filename = $this->extensionPathResolver->getPath('module', 'oe_link_lists_rss_source_test') . '/fixtures/aggregator_test_rss091_extra_item.xml';
            }
            break;
        }

        if ($filename) {
          $response = new Response(200, [], file_get_contents($filename));
          return new FulfilledPromise($response);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

}
