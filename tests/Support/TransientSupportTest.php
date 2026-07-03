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

use Middag\WordPress\Support\TransientSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TransientSupport::class)]
final class TransientSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_transients'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_transients']);
    }

    #[Test]
    public function setGetDeleteRoundTrip(): void
    {
        self::assertTrue(TransientSupport::set('acme_key', ['a' => 1], 300));
        self::assertSame(['a' => 1], TransientSupport::get('acme_key'));
        self::assertTrue(TransientSupport::delete('acme_key'));
        self::assertFalse(TransientSupport::get('acme_key'));
    }

    #[Test]
    public function rememberProducesOnceAndCaches(): void
    {
        $calls = 0;
        $producer = static function () use (&$calls): string {
            ++$calls;

            return 'produced';
        };

        self::assertSame('produced', TransientSupport::remember('acme_memo', 60, $producer));
        self::assertSame('produced', TransientSupport::remember('acme_memo', 60, $producer));
        self::assertSame(1, $calls, 'producer must run only on miss');
    }
}
