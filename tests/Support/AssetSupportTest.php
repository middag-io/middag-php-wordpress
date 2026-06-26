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

use Middag\WordPress\Support\AssetSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AssetSupport::class)]
final class AssetSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_enqueued_scripts'] = [];
        $GLOBALS['__wp_test_enqueued_styles'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_enqueued_scripts'], $GLOBALS['__wp_test_enqueued_styles']);
    }

    #[Test]
    public function enqueueScriptDelegatesWithDepsVersionAndFooterFlag(): void
    {
        AssetSupport::enqueueScript('middag-app', 'https://example.test/app.js', ['wp-element'], '1.2.3', true);

        $script = $GLOBALS['__wp_test_enqueued_scripts']['middag-app'] ?? null;
        self::assertNotNull($script, 'the script was not enqueued');
        self::assertSame('https://example.test/app.js', $script['src']);
        self::assertSame(['wp-element'], $script['deps']);
        self::assertSame('1.2.3', $script['ver']);
        self::assertTrue($script['args']);
    }

    #[Test]
    public function enqueueStyleDelegatesWithVersion(): void
    {
        AssetSupport::enqueueStyle('middag-app', 'https://example.test/app.css', [], '1.2.3');

        $style = $GLOBALS['__wp_test_enqueued_styles']['middag-app'] ?? null;
        self::assertNotNull($style, 'the style was not enqueued');
        self::assertSame('https://example.test/app.css', $style['src']);
        self::assertSame('1.2.3', $style['ver']);
    }
}
