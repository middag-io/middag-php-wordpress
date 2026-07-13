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
 * Architecture guard (O5-LIB-05 / O5-LIB-07): the library must not bundle
 * application-token / JWT authentication. That flow (issue/verify/refresh access
 * + refresh tokens, org/roles/scopes claims) is product logic and now lives in
 * the proprietary core (WordPressTokenService); it must never regress back into
 * this Apache-2.0 library. Host request-auth resolution stays behind the
 * RequestAuthenticatorInterface seam; the JWT engine does not.
 *
 * The guard is a source scan for the `Firebase\JWT` dependency (the token codec)
 * — the concrete tell of bundled token auth.
 *
 * @internal
 */
#[CoversNothing]
final class NoBundledTokenAuthTest extends TestCase
{
    #[Test]
    #[DataProvider('sourceFileProvider')]
    public function sourceFileDoesNotBundleJwtTokenAuth(string $path): void
    {
        $contents = (string) file_get_contents($path);

        self::assertStringNotContainsString(
            'Firebase\JWT',
            $contents,
            sprintf(
                '%s references Firebase\JWT — app-token/JWT logic is product code and belongs in the proprietary core (WordPressTokenService), never in this OSS library.',
                basename($path),
            ),
        );
    }

    /**
     * Every PHP source file under src/.
     *
     * @return array<string, array{string}>
     */
    public static function sourceFileProvider(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $files = [];

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

        ksort($files);

        return $files;
    }
}
