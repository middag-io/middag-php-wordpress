<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Frontend;

use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Frontend\InertiaAdapter;
use Middag\WordPress\Kernel\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterEnqueueTest extends TestCase
{
    protected function setUp(): void
    {
        HostContext::reset();
        $GLOBALS['__wp_test_enqueued_scripts'] = [];
        $GLOBALS['__wp_test_enqueued_styles'] = [];
    }

    protected function tearDown(): void
    {
        HostContext::reset();
        unset($GLOBALS['__wp_test_enqueued_scripts'], $GLOBALS['__wp_test_enqueued_styles']);
    }

    #[Test]
    public function enqueuesScriptAndStyleCacheBustedByTheHostAssetVersion(): void
    {
        // Sentinel version distinct from the fallback ('5.0.0') so the assertion
        // proves the version is SOURCED FROM the host context, not hard-coded.
        HostContext::set(new WpComponentContext('my-plugin', 'sentinel-7.7.7'));

        InertiaAdapter::enqueueAssets(
            'middag-app',
            'https://example.test/wp-content/plugins/my/build/app.js',
            'https://example.test/wp-content/plugins/my/build/app.css',
            ['wp-element'],
        );

        $script = $GLOBALS['__wp_test_enqueued_scripts']['middag-app'] ?? null;
        self::assertNotNull($script, 'the script bundle was not enqueued');
        self::assertSame('https://example.test/wp-content/plugins/my/build/app.js', $script['src']);
        self::assertSame(['wp-element'], $script['deps']);
        self::assertTrue($script['args'], 'the bundle must load in the footer');
        self::assertSame('sentinel-7.7.7', $script['ver']);

        $style = $GLOBALS['__wp_test_enqueued_styles']['middag-app'] ?? null;
        self::assertNotNull($style, 'the stylesheet was not enqueued');
        self::assertSame('https://example.test/wp-content/plugins/my/build/app.css', $style['src']);
        self::assertSame('sentinel-7.7.7', $style['ver']);
    }

    #[Test]
    public function skipsTheStylesheetWhenNoStyleUrlIsGiven(): void
    {
        HostContext::set(new WpComponentContext('my-plugin', '1.0.0'));

        InertiaAdapter::enqueueAssets('middag-app', 'https://example.test/app.js');

        self::assertArrayHasKey('middag-app', $GLOBALS['__wp_test_enqueued_scripts']);
        self::assertArrayNotHasKey('middag-app', $GLOBALS['__wp_test_enqueued_styles']);
    }

    #[Test]
    public function fallsBackToTheSafeDefaultVersionWhenNoHostConfigured(): void
    {
        InertiaAdapter::enqueueAssets('middag-app', 'https://example.test/app.js');

        self::assertSame('5.0.0', $GLOBALS['__wp_test_enqueued_scripts']['middag-app']['ver']);
    }
}
