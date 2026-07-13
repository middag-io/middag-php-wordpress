<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture guard (O5-LIB-02): no routing source file may hard-code the
 * lowercase brand literal `middag` in a PHP string literal. Namespaces, slugs,
 * and REST namespaces must derive from the host component name so two plugins
 * in the same request stay disjoint — a brand default reintroduces coupling.
 *
 * The scan is token-based (only T_CONSTANT_ENCAPSED_STRING contents), so it is
 * immune to the `michael@middag.io` license header and the `Middag\` namespace
 * (which is uppercase anyway). Case-sensitive: only the lowercase literal is a
 * violation.
 *
 * @internal
 */
#[CoversNothing]
final class RoutingBrandLiteralTest extends TestCase
{
    #[Test]
    #[DataProvider('routingSourceFileProvider')]
    public function routingSourceFileHasNoBrandStringLiteral(string $path): void
    {
        $tokens = token_get_all((string) file_get_contents($path));

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            self::assertStringNotContainsString(
                'middag',
                $token[1],
                sprintf(
                    '%s hard-codes the brand literal %s (line %d) — derive it from componentName() instead.',
                    basename($path),
                    $token[1],
                    $token[2],
                ),
            );
        }
    }

    /**
     * Every PHP source file across the three routing surfaces (REST + PUBLIC in
     * Http/Routing, ADMIN in Admin/).
     *
     * @return array<string, array{string}>
     */
    public static function routingSourceFileProvider(): array
    {
        $roots = [
            dirname(__DIR__, 2) . '/src/Http/Routing',
            dirname(__DIR__, 2) . '/src/Admin',
        ];

        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $files[$file->getPathname()] = [$file->getPathname()];
            }
        }

        ksort($files);

        return $files;
    }
}
