<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Adapter-wide namespace boundary.
 *
 * The adapter is an Apache-2.0 OSS package that builds only on the OSS MIDDAG
 * libraries. Every `Middag\` namespace that a `src/` file names, in code,
 * strings, comments or docblocks, in the single- or the double-backslash form,
 * must be one of the OSS namespaces below. Any other `Middag\` namespace fails
 * the build. The rule is an allowlist: it names no other package.
 *
 * Product names in string literals (table names, hooks, defaults) are guarded
 * by {@see Architecture\NoProductNamedDefaultTest}.
 *
 * @internal
 */
#[CoversNothing]
final class AdapterPluginIsolationTest extends TestCase
{
    private const string SRC_DIR = __DIR__ . '/../src';

    /** The first segment after `Middag\` of every namespace `src/` may name. */
    private const array OSS_NAMESPACES = ['WordPress', 'Framework', 'Ui'];

    private const string NAMESPACE_PATTERN = '/\bMiddag\\\+([A-Za-z_][A-Za-z0-9_]*)/i';

    #[Test]
    public function sourceTreeIsNotEmpty(): void
    {
        self::assertNotSame([], iterator_to_array(self::sourceFileProvider()), 'src/ has no PHP file to scan.');
    }

    #[Test]
    #[DataProvider('sourceFileProvider')]
    public function fileNamesOnlyOssMiddagNamespaces(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        preg_match_all(self::NAMESPACE_PATTERN, $source, $matches);

        $allowed = array_map(strtolower(...), self::OSS_NAMESPACES);
        $foreign = array_values(array_unique(array_filter(
            $matches[1],
            static fn (string $segment): bool => !in_array(strtolower($segment), $allowed, true),
        )));

        self::assertSame(
            [],
            $foreign,
            sprintf(
                'Adapter file names a Middag namespace outside %s: keep the adapter on the OSS libraries.',
                implode(', ', self::OSS_NAMESPACES),
            ),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sourceFileProvider(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::SRC_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $label = substr($path, strlen(self::SRC_DIR) + 1);

            yield $label => [$path];
        }
    }

    #[Test]
    public function patternCatchesEveryNamespaceForm(): void
    {
        $samples = [
            'use Middag\Other\Thing;' => 'Other',
            '"Middag\\\Other\\\Thing"' => 'Other',
            'new \middag\other\Thing()' => 'other',
            'use Middag\Framework\Kernel;' => 'Framework',
        ];

        foreach ($samples as $source => $segment) {
            self::assertSame(1, preg_match(self::NAMESPACE_PATTERN, $source, $match), $source);
            self::assertSame($segment, $match[1], $source);
        }
    }
}
