<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Kernel\Loader;

use FilesystemIterator;
use Middag\WordPress\Kernel\Loader\WordPressHookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 */
#[CoversClass(WordPressHookfileLoader::class)]
final class WordPressHookfileLoaderTest extends TestCase
{
    private string $tmpRoot;

    private string $contentDir;

    private string $themeSlug = 'middag-theme';

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/middag-wp-hookfile-' . uniqid('', true);
        $this->contentDir = $this->tmpRoot . '/wp-content';
        mkdir($this->contentDir . '/themes/' . $this->themeSlug, 0o777, true);

        $GLOBALS['__middag_test_wp_filters'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['__middag_test_wp_filters'] = [];
        $this->deleteDir($this->tmpRoot);
    }

    public function testDiscoverReturnsEmptyWhenNoFilesExist(): void
    {
        $loader = $this->loader();
        self::assertSame([], $loader->discover());
    }

    public function testDiscoverFindsContentAndThemeAndFilterPathsInOrder(): void
    {
        $content_hook = $this->writeHook($this->contentDir . '/middag_hooks.php');
        $theme_hook = $this->writeHook($this->contentDir . '/themes/' . $this->themeSlug . '/middag_hooks.php');

        $plugin_hook = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');
        $GLOBALS['__middag_test_wp_filters']['middag_hookfiles'] = static fn (array $paths): array => array_merge($paths, [$plugin_hook]);

        $discovered = $this->loader()->discover();

        self::assertSame(
            [$content_hook, $theme_hook, $plugin_hook],
            $discovered,
            'Sources must be returned in content -> theme -> filter order.',
        );
    }

    public function testDiscoverSkipsMissingFilterPaths(): void
    {
        $real_hook = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');

        $GLOBALS['__middag_test_wp_filters']['middag_hookfiles'] = static fn (): array => [
            '/nonexistent/path.php',
            $real_hook,
            123,
        ];

        self::assertSame([$real_hook], $this->loader()->discover());
    }

    public function testDiscoverOmitsThemeSourceWhenStylesheetBlank(): void
    {
        $content_hook = $this->writeHook($this->contentDir . '/middag_hooks.php');
        $this->writeHook($this->contentDir . '/themes/' . $this->themeSlug . '/middag_hooks.php');

        $loader = new WordPressHookfileLoader(
            contentDir: $this->contentDir,
            activeTheme: '',
        );
        self::assertSame([$content_hook], $loader->discover());
    }

    public function testCustomFilterNameIsolatesContributions(): void
    {
        $contributed = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');

        $GLOBALS['__middag_test_wp_filters']['custom_brand_hookfiles'] = static fn (): array => [$contributed];
        $GLOBALS['__middag_test_wp_filters']['middag_hookfiles'] = static fn (): array => ['/should/be/ignored.php'];

        $loader = new WordPressHookfileLoader(
            filterName: 'custom_brand_hookfiles',
            contentDir: $this->contentDir,
            activeTheme: $this->themeSlug,
        );
        self::assertSame([$contributed], $loader->discover());
    }

    public function testFilterReturningNonArrayIsIgnored(): void
    {
        $GLOBALS['__middag_test_wp_filters']['middag_hookfiles'] = static fn (): string => 'not-an-array';

        self::assertSame([], $this->loader()->discover());
    }

    private function loader(): WordPressHookfileLoader
    {
        return new WordPressHookfileLoader(
            contentDir: $this->contentDir,
            activeTheme: $this->themeSlug,
        );
    }

    private function writeHook(string $path): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, "<?php\n// test hookfile\n");

        return $path;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($dir);
    }
}
