<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\CacheSupportPsr16;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheSupportPsr16::class)]
final class CacheSupportPsr16Test extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_object_cache'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_object_cache']);
    }

    #[Test]
    public function setGetDeleteRoundTrips(): void
    {
        $cache = new CacheSupportPsr16('item');

        self::assertTrue($cache->set('item_5', ['id' => 5]));
        self::assertSame(['id' => 5], $cache->get('item_5'));
        self::assertTrue($cache->has('item_5'));

        self::assertTrue($cache->delete('item_5'));
        self::assertNull($cache->get('item_5'));
        self::assertFalse($cache->has('item_5'));
    }

    #[Test]
    public function getReturnsDefaultOnMiss(): void
    {
        $cache = new CacheSupportPsr16('item');

        self::assertSame('fallback', $cache->get('absent', 'fallback'));
    }

    #[Test]
    public function storedFalseIsReturnedAsIsNotTreatedAsMiss(): void
    {
        $cache = new CacheSupportPsr16('item');

        $cache->set('flag', false);

        self::assertFalse($cache->get('flag', 'default-not-used'));
        self::assertTrue($cache->has('flag'));
    }

    #[Test]
    public function clearPurgesTheAreaViaGenerationBump(): void
    {
        $cache = new CacheSupportPsr16('item');
        $cache->set('a', 1);
        $cache->set('b', 2);

        self::assertTrue($cache->clear());

        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertFalse($cache->has('a'));
    }

    #[Test]
    public function clearIsScopedToItsOwnArea(): void
    {
        $items = new CacheSupportPsr16('item');
        $other = new CacheSupportPsr16('other');

        $items->set('shared_key', 'items-value');
        $other->set('shared_key', 'other-value');

        $items->clear();

        self::assertNull($items->get('shared_key'), 'purged area is empty');
        self::assertSame('other-value', $other->get('shared_key'), 'sibling area is untouched');
    }

    #[Test]
    public function areasAreIsolatedForTheSameKey(): void
    {
        $items = new CacheSupportPsr16('item');
        $other = new CacheSupportPsr16('other');

        $items->set('k', 'items');
        $other->set('k', 'other');

        self::assertSame('items', $items->get('k'));
        self::assertSame('other', $other->get('k'));
    }

    #[Test]
    public function multipleOperationsRoundTrip(): void
    {
        $cache = new CacheSupportPsr16('item');

        self::assertTrue($cache->setMultiple(['x' => 1, 'y' => 2, 'z' => 3]));

        self::assertSame(
            ['x' => 1, 'y' => 2, 'missing' => 'def'],
            iterator_to_array($this->asIterator($cache->getMultiple(['x', 'y', 'missing'], 'def'))),
        );

        self::assertTrue($cache->deleteMultiple(['x', 'y']));
        self::assertNull($cache->get('x'));
        self::assertNull($cache->get('y'));
        self::assertSame(3, $cache->get('z'));
    }

    #[Test]
    public function integerTtlIsAcceptedAndValuePersistsWithinRequest(): void
    {
        $cache = new CacheSupportPsr16('item');

        self::assertTrue($cache->set('ttl_key', 'v', 3600));
        self::assertSame('v', $cache->get('ttl_key'));
    }

    /**
     * @param iterable<string, mixed> $values
     *
     * @return iterable<string, mixed>
     */
    private function asIterator(iterable $values): iterable
    {
        yield from $values;
    }
}
