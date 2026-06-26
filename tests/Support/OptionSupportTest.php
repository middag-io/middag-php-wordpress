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

use Middag\WordPress\Support\OptionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OptionSupport::class)]
final class OptionSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_options']);
    }

    #[Test]
    public function getReturnsTheStoredOptionValue(): void
    {
        $GLOBALS['__wp_test_options']['middag_sentry_dsn'] = 'https://sentry.example/123';

        self::assertSame('https://sentry.example/123', OptionSupport::get('middag_sentry_dsn'));
    }

    #[Test]
    public function getReturnsFalseByDefaultWhenTheOptionIsUnset(): void
    {
        self::assertFalse(OptionSupport::get('middag_missing'));
    }

    #[Test]
    public function getReturnsTheSuppliedDefaultWhenTheOptionIsUnset(): void
    {
        self::assertSame('fallback', OptionSupport::get('middag_missing', 'fallback'));
    }
}
