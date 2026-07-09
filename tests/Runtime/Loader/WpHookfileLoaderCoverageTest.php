<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Runtime\Loader;

use Middag\WordPress\Runtime\Loader\WpHookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the default (non-injected) resolution branches of the loader:
 * `WP_CONTENT_DIR` constant lookup and `get_stylesheet()` function lookup.
 * The injected-override branches are covered by {@see WpHookfileLoaderTest}.
 *
 * @internal
 */
#[CoversClass(WpHookfileLoader::class)]
final class WpHookfileLoaderCoverageTest extends TestCase
{
    #[Test]
    public function discoverReturnsEmptyWhenContentDirConstantIsUndefined(): void
    {
        // In the main test process WP_CONTENT_DIR is never defined, so the
        // constant lookup returns null and discovery yields nothing.
        self::assertFalse(defined('WP_CONTENT_DIR'), 'guard: this test needs WP_CONTENT_DIR undefined');

        $loader = new WpHookfileLoader();

        self::assertSame([], $loader->discover());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function discoverResolvesContentDirConstantAndStylesheetSlug(): void
    {
        $contentDir = sys_get_temp_dir() . '/middag-wp-hookfile-cov-' . uniqid('', true);
        $themeSlug = 'middag-cov-theme';
        mkdir($contentDir . '/themes/' . $themeSlug, 0o777, true);

        // A real hookfile at the content root so maybeAdd() keeps a path,
        // proving the WP_CONTENT_DIR branch actually fed discovery.
        file_put_contents($contentDir . '/middag_hooks.php', "<?php\n");

        define('WP_CONTENT_DIR', $contentDir);
        $GLOBALS['__middag_test_wp_stylesheet'] = $themeSlug;

        $loader = new WpHookfileLoader();
        $paths = $loader->discover();

        self::assertContains($contentDir . '/middag_hooks.php', $paths);

        // cleanup
        unlink($contentDir . '/middag_hooks.php');
        rmdir($contentDir . '/themes/' . $themeSlug);
        rmdir($contentDir . '/themes');
        rmdir($contentDir);
    }
}
