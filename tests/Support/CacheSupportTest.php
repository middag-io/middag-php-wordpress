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

use Middag\WordPress\Support\CacheSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheSupport::class)]
final class CacheSupportTest extends TestCase
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
    public function setGetDeleteWithGroupsAndFoundFlag(): void
    {
        self::assertTrue(CacheSupport::set('k', 'v', 'acme'));

        $found = null;
        self::assertSame('v', CacheSupport::get('k', 'acme', $found));
        self::assertTrue($found);

        self::assertTrue(CacheSupport::delete('k', 'acme'));
        self::assertFalse(CacheSupport::get('k', 'acme', $found));
        self::assertFalse($found, 'found distinguishes a cached false from a miss');
    }

    #[Test]
    public function flushClearsEveryGroup(): void
    {
        CacheSupport::set('a', 1, 'g1');
        CacheSupport::set('b', 2, 'g2');

        self::assertTrue(CacheSupport::flush());
        self::assertFalse(CacheSupport::get('a', 'g1'));
        self::assertFalse(CacheSupport::get('b', 'g2'));
    }
}
