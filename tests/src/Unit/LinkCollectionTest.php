<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_link_lists\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_link_lists\DefaultEntityLink;
use Drupal\oe_link_lists\DefaultLink;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the link collection class.
 *
 * @coversDefaultClass \Drupal\oe_link_lists\LinkCollection
 */
class LinkCollectionTest extends UnitTestCase {

  /**
   * Tests the class constructor.
   *
   * In order to test that the constructor works correctly, the toArray() method
   * is also used and thus marked as tested here.
   *
   * @covers ::__construct
   * @covers ::toArray
   */
  public function testConstructor(): void {
    $test_data = $this->getTestData();
    $collection = new LinkCollection($test_data);
    $this->assertSame($test_data, $collection->toArray());

    // Create a collection with an invalid element specified.
    $test_data[] = new Link($this->randomMachineName(), new Url('<front>'));
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid argument type: expected Drupal\oe_link_lists\LinkInterface, got Drupal\Core\Link.');
    new LinkCollection($test_data);
  }

  /**
   * Tests the add method.
   *
   * @covers ::add
   */
  public function testAdd(): void {
    $collection = new LinkCollection();

    $link_one = new DefaultLink(new Url('<front>'), $this->randomMachineName(), []);
    $collection->add($link_one);
    $this->assertSame([
      0 => $link_one,
    ], $collection->toArray());

    $link_two = new DefaultEntityLink(new Url('<front>'), $this->randomMachineName(), []);
    $collection->add($link_two);
    $this->assertSame([
      0 => $link_one,
      1 => $link_two,
    ], $collection->toArray());
  }

  /**
   * Tests the isEmpty() method.
   *
   * @return \Drupal\oe_link_lists\LinkCollection
   *   A test collection.
   *
   * @covers ::isEmpty
   */
  public function testIsEmpty(): LinkCollection {
    $collection = new LinkCollection();
    $this->assertTrue($collection->isEmpty());
    $collection->add(new DefaultLink(new Url('<front>'), $this->randomMachineName(), []));
    $this->assertFalse($collection->isEmpty());
    $collection->add(new DefaultLink(new Url('<front>'), $this->randomMachineName(), []));
    $this->assertFalse($collection->isEmpty());

    return $collection;
  }

  /**
   * Tests the clear() method.
   *
   * @param \Drupal\oe_link_lists\LinkCollection $collection
   *   A test collection.
   *
   * @depends testIsEmpty
   * @covers ::clear
   */
  public function testClear(LinkCollection $collection): void {
    $collection->clear();
    $this->assertTrue($collection->isEmpty());
    $this->assertEquals([], $collection->toArray());
  }

  /**
   * Tests the offsetExists() method.
   *
   * @covers ::offsetExists
   */
  public function testOffsetExists(): void {
    $collection = new LinkCollection($this->getTestData());

    $this->assertTrue(isset($collection['a']));
    $this->assertTrue(isset($collection[1]));
    $this->assertTrue(isset($collection[NULL]));
    $this->assertFalse(isset($collection['foo']));
    $this->assertFalse(isset($collection[2]));
  }

  /**
   * Tests the offsetGet() method.
   *
   * @covers ::offsetGet
   */
  public function testOffsetGet(): void {
    $test_data = $this->getTestData();
    $collection = new LinkCollection($test_data);

    $this->assertSame($test_data['a'], $collection['a']);
    $this->assertSame($test_data[1], $collection[1]);
    $this->assertSame($test_data[NULL], $collection[NULL]);
    $this->assertNull($collection[2]);
  }

  /**
   * Tests the offsetSet() method.
   *
   * @covers ::offsetSet
   */
  public function testOffsetSet(): void {
    $collection = new LinkCollection();

    $link_one = new DefaultLink(new Url('<front>'), $this->randomMachineName(), []);
    $link_two = new DefaultLink(new Url('<front>'), $this->randomMachineName(), []);
    $link_three = new DefaultEntityLink(new Url('<front>'), $this->randomMachineName(), []);
    $collection[] = $link_one;
    $collection[3] = $link_two;
    $collection['a'] = $link_three;

    $this->assertSame([
      0 => $link_one,
      3 => $link_two,
      'a' => $link_three,
    ], $collection->toArray());

    // Test overwriting an element.
    $link_four = new DefaultEntityLink(new Url('<front>'), $this->randomMachineName(), []);
    $collection[3] = $link_four;
    $this->assertSame([
      0 => $link_one,
      3 => $link_four,
      'a' => $link_three,
    ], $collection->toArray());
  }

  /**
   * Tests that the offsetSet() method throws exceptions for invalid objects.
   *
   * @param mixed $argument
   *   A value to add to the collection.
   * @param string $exception_message
   *   The expected exception message.
   *
   * @covers ::offsetSet
   * @dataProvider invalidArgumentDataProvider
   */
  public function testOffsetSetInvalidArgument($argument, string $exception_message): void {
    $collection = new LinkCollection();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($exception_message);
    $collection[] = $argument;
  }

  /**
   * Tests the offsetUnset() method.
   *
   * @covers ::offsetUnset
   */
  public function testOffsetUnset(): void {
    $test_data = $this->getTestData();
    $collection = new LinkCollection($test_data);

    unset($collection[1]);
    unset($test_data[1]);
    $this->assertSame($test_data, $collection->toArray());

    unset($collection['a']);
    unset($test_data['a']);
    $this->assertSame($test_data, $collection->toArray());

    unset($collection[NULL]);
    $this->assertEquals([], $collection->toArray());
  }

  /**
   * Tests the iterability of the collection.
   *
   * This method tests that the class is iterable.
   *
   * @covers ::getIterator
   */
  public function testGetIterator(): void {
    $test_data = $this->getTestData();
    $collection = new LinkCollection($test_data);

    $i = 0;
    foreach ($collection as $key => $value) {
      ++$i;
      $this->assertSame($test_data[$key], $value);
    }

    $this->assertEquals(count($test_data), $i);
  }

  /**
   * Tests the getCacheTags() method.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTags(): void {
    $this->setUpCacheContextsManager();

    $collection = new LinkCollection();
    $this->assertEquals([], $collection->getCacheTags());

    [$link_one, $link_two] = $this->getCacheabilityTestData();
    $collection->add($link_one);
    $this->assertEquals(['test_tag_1'], $collection->getCacheTags());

    $collection->add($link_two);
    $this->assertEqualsCanonicalizing(['test_tag_1', 'test_tag_2'], $collection->getCacheTags());

    $collection->addCacheTags(['collection_tag_1', 'collection_tag_2']);
    $this->assertEqualsCanonicalizing([
      'collection_tag_1',
      'collection_tag_2',
      'test_tag_1',
      'test_tag_2',
    ], $collection->getCacheTags());

    // Verify that the cache tags are calculated runtime by adding extra
    // cache metadata to a link present in the collection.
    $link_one->addCacheTags(['test_tag_3']);
    $this->assertEqualsCanonicalizing([
      'collection_tag_1',
      'collection_tag_2',
      'test_tag_1',
      'test_tag_2',
      'test_tag_3',
    ], $collection->getCacheTags());

    unset($collection[0]);
    $this->assertEqualsCanonicalizing([
      'collection_tag_1',
      'collection_tag_2',
      'test_tag_1',
      'test_tag_2',
    ], $collection->getCacheTags());

    unset($collection[1]);
    $this->assertEqualsCanonicalizing([
      'collection_tag_1',
      'collection_tag_2',
    ], $collection->getCacheTags());
  }

  /**
   * Tests the getCacheContexts() method.
   *
   * @covers ::getCacheContexts
   */
  public function testGetCacheContexts(): void {
    $this->setUpCacheContextsManager();

    $collection = new LinkCollection();
    $this->assertEquals([], $collection->getCacheContexts());

    [$link_one, $link_two] = $this->getCacheabilityTestData();
    $collection->add($link_one);
    $this->assertEquals(['test_context_1'], $collection->getCacheContexts());

    $collection->add($link_two);
    $this->assertEqualsCanonicalizing(['test_context_1', 'test_context_2'], $collection->getCacheContexts());

    $collection->addCacheContexts([
      'collection_context_1',
      'collection_context_2',
    ]);
    $this->assertEqualsCanonicalizing([
      'collection_context_1',
      'collection_context_2',
      'test_context_1',
      'test_context_2',
    ], $collection->getCacheContexts());

    // Verify that the cache contexts are calculated runtime by adding extra
    // cache metadata to a link present in the collection.
    $link_one->addCacheContexts(['test_context_3']);
    $this->assertEqualsCanonicalizing([
      'collection_context_1',
      'collection_context_2',
      'test_context_1',
      'test_context_2',
      'test_context_3',
    ], $collection->getCacheContexts());

    unset($collection[0]);
    $this->assertEqualsCanonicalizing([
      'collection_context_1',
      'collection_context_2',
      'test_context_1',
      'test_context_2',
    ], $collection->getCacheContexts());

    unset($collection[1]);
    $this->assertEqualsCanonicalizing([
      'collection_context_1',
      'collection_context_2',
    ], $collection->getCacheContexts());
  }

  /**
   * Tests the getCacheMaxAge() method.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAge(): void {
    $this->setUpCacheContextsManager();

    $collection = new LinkCollection();
    $this->assertEquals(Cache::PERMANENT, $collection->getCacheMaxAge());

    $collection->mergeCacheMaxAge(7200);
    $this->assertEquals(7200, $collection->getCacheMaxAge());

    [$link_one, $link_two] = $this->getCacheabilityTestData();
    $collection->add($link_one);
    $this->assertEquals(3600, $collection->getCacheMaxAge());

    $collection->add($link_two);
    $this->assertEquals(1800, $collection->getCacheMaxAge());

    unset($collection[0]);
    $this->assertEquals(1800, $collection->getCacheMaxAge());

    unset($collection[1]);
    $this->assertEquals(7200, $collection->getCacheMaxAge());
  }

  /**
   * Provides a list of invalid arguments for a link collection.
   *
   * @return array
   *   A series of test elements and related expected exception.
   */
  public function invalidArgumentDataProvider(): array {
    return [
      'string' => [
        'test',
        'Invalid argument type: expected Drupal\oe_link_lists\LinkInterface, got string.',
      ],
      'double' => [
        3.14,
        'Invalid argument type: expected Drupal\oe_link_lists\LinkInterface, got double.',
      ],
      'object' => [
        new \stdClass(),
        'Invalid argument type: expected Drupal\oe_link_lists\LinkInterface, got stdClass.',
      ],
      'url class' => [
        new Url('<front>'),
        'Invalid argument type: expected Drupal\oe_link_lists\LinkInterface, got Drupal\Core\Url.',
      ],
    ];
  }

  /**
   * Provides an associative array of link objects.
   *
   * @return array
   *   An associative array of test data.
   */
  protected function getTestData(): array {
    return [
      'a' => (new DefaultLink(new Url('<front>'), $this->randomMachineName(), [])),
      1 => new DefaultEntityLink(new Url('<front>'), $this->randomMachineName(), []),
      NULL => new DefaultLink(new Url('<front>'), $this->randomMachineName(), []),
    ];
  }

  /**
   * Sets up a fake cache contexts manager service.
   *
   * This is required to call ->addCacheContexts() method on links.
   */
  protected function setUpCacheContextsManager(): void {
    $container = new ContainerBuilder();
    $cache_contexts_manager_mock = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager_mock->expects($this->any())
      ->method('assertValidTokens')
      ->will($this->returnValue(TRUE));
    $container->set('cache_contexts_manager', $cache_contexts_manager_mock);
    \Drupal::setContainer($container);
  }

  /**
   * Provides an array of link objects with cache information.
   *
   * @return array
   *   An array of test data.
   */
  protected function getCacheabilityTestData(): array {
    $link_one = new DefaultLink(new Url('<front>'), $this->randomMachineName(), []);
    $link_one
      ->addCacheTags(['test_tag_1'])
      ->addCacheContexts(['test_context_1'])
      ->mergeCacheMaxAge(3600);

    $link_two = new DefaultLink(new Url('<front>'), $this->randomMachineName(), []);
    $link_two
      ->addCacheTags(['test_tag_1', 'test_tag_2'])
      ->addCacheContexts(['test_context_1', 'test_context_2'])
      ->mergeCacheMaxAge(1800);

    return [$link_one, $link_two];
  }

}
