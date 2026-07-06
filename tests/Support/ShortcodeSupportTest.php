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

use Middag\WordPress\Support\ShortcodeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ShortcodeSupport::class)]
final class ShortcodeSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_shortcodes'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_shortcodes']);
    }

    #[Test]
    public function addDelegatesToWordPress(): void
    {
        $callback = static fn (): string => 'rendered';

        ShortcodeSupport::add('middag_box', $callback);

        self::assertArrayHasKey('middag_box', $GLOBALS['__wp_test_shortcodes']);
        self::assertSame($callback, $GLOBALS['__wp_test_shortcodes']['middag_box']);
    }

    #[Test]
    public function removeDelegatesToWordPress(): void
    {
        ShortcodeSupport::add('middag_box', static fn (): string => 'rendered');
        self::assertArrayHasKey('middag_box', $GLOBALS['__wp_test_shortcodes']);

        ShortcodeSupport::remove('middag_box');

        self::assertArrayNotHasKey('middag_box', $GLOBALS['__wp_test_shortcodes']);
    }

    #[Test]
    public function renderDelegatesToDoShortcode(): void
    {
        self::assertSame('[middag_box]hello[/middag_box]', ShortcodeSupport::render('[middag_box]hello[/middag_box]'));
    }
}
