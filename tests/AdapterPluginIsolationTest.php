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
 * Adapter-wide product-coupling guard (Article-I boundary).
 *
 * The middag-io/wordpress adapter is a generic, reusable Apache-2.0 WordPress
 * package. It must never reference a consumer plugin's namespace
 * ({@code Middag\Account\...}), the proprietary CORE/Licensing/DevTools
 * namespaces, or hard-code product gold tables. This test source-scans every
 * adapter `src/` file so any regression fails loudly — it materialises the
 * `check:boundaries` gate promised in the package docs. Uses source inspection
 * rather than class loading to avoid pulling in a full WordPress runtime.
 *
 * @internal
 */
#[CoversNothing]
final class AdapterPluginIsolationTest extends TestCase
{
    private const SRC_DIR = __DIR__ . '/../src';

    /**
     * Namespace prefixes the OSS adapter must never reference.
     *
     * These literals are intentional enforcement targets — the non-OSS MIDDAG
     * and consumer-plugin namespaces this guard source-scans for. They are NOT
     * dependencies of the adapter and exist only so a regression fails loudly;
     * they are deliberately kept here (and out of the public docs) per the OSS
     * boundary policy.
     */
    private const FORBIDDEN_NAMESPACES = [
        'Middag\Account\\',
        'Middag\Core\\',
        'Middag\Licensing\\',
        'Middag\DevTools\\',
    ];

    /** Gold-table string literals the adapter must not hard-code. */
    private const FORBIDDEN_TABLE_LITERALS = [
        "'middag_items'",
        "'middag_itemmeta'",
        "'middag_item_revision'",
        "'middag_activity_feed'",
        "'middag_audit_log'",
        "'middag_job'",
    ];

    #[Test]
    #[DataProvider('sourceFileProvider')]
    public function fileDoesNotReferencePluginOrProprietaryNamespaces(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach (self::FORBIDDEN_NAMESPACES as $namespace) {
            self::assertStringNotContainsString(
                $namespace,
                $source,
                sprintf('Adapter file must not reference %s — keep the adapter product-agnostic.', $namespace),
            );
        }
    }

    #[Test]
    #[DataProvider('sourceFileProvider')]
    public function fileDoesNotHardcodeGoldTables(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach (self::FORBIDDEN_TABLE_LITERALS as $literal) {
            self::assertStringNotContainsString(
                $literal,
                $source,
                sprintf('Adapter file must not hard-code gold table literal %s — accept it as input.', $literal),
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sourceFileProvider(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::SRC_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $base = realpath(self::SRC_DIR) ?: self::SRC_DIR;

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $label = substr($path, strlen($base) + 1);

            yield $label => [$path];
        }
    }
}
