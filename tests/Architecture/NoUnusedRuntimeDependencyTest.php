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

use Composer\InstalledVersions;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture guard: every package in the `require` section of composer.json
 * is used by `src/`. A runtime requirement that no source file references is
 * installed into every consumer for nothing; a token codec required "just in
 * case" is how a bundled token stack starts.
 *
 * A package counts as used when `src/` names one of the PSR-4 namespaces its
 * own composer.json declares. The platform entries (`php`, `ext-*`) are not
 * packages and are skipped. The guard fails closed: a required package with no
 * install path or no PSR-4 namespace fails, and so does an empty `src/`.
 *
 * @internal
 */
#[CoversNothing]
final class NoUnusedRuntimeDependencyTest extends TestCase
{
    #[Test]
    #[DataProvider('requiredPackageProvider')]
    public function requiredPackageIsUsedBySource(string $package): void
    {
        $namespaces = $this->psr4Namespaces($package);

        self::assertNotSame([], $namespaces, sprintf('%s declares no PSR-4 namespace; the guard cannot tell whether src/ uses it.', $package));

        $source = $this->sourceCode();

        foreach ($namespaces as $namespace) {
            if (str_contains($source, $namespace)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        self::fail(sprintf(
            '%s is required in composer.json but no file under src/ uses its namespaces (%s). Remove the requirement, or move it to require-dev.',
            $package,
            implode(', ', $namespaces),
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredPackageProvider(): array
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertIsArray($composer['require'] ?? null);

        $packages = [];

        foreach (array_keys($composer['require']) as $package) {
            if ($package === 'php') {
                continue;
            }

            if (str_starts_with((string) $package, 'ext-')) {
                continue;
            }

            $packages[(string) $package] = [(string) $package];
        }

        self::assertNotSame([], $packages, 'composer.json requires no package; the guard has nothing to check.');

        return $packages;
    }

    /**
     * @return list<string>
     */
    private function psr4Namespaces(string $package): array
    {
        $installPath = InstalledVersions::isInstalled($package) ? InstalledVersions::getInstallPath($package) : null;

        if ($installPath === null || !is_file($installPath . '/composer.json')) {
            self::fail(sprintf('%s is required in composer.json but is not installed.', $package));
        }

        $manifest = json_decode((string) file_get_contents($installPath . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $psr4 = is_array($manifest) && is_array($manifest['autoload']['psr-4'] ?? null) ? $manifest['autoload']['psr-4'] : [];

        $namespaces = [];

        foreach (array_keys($psr4) as $namespace) {
            $trimmed = rtrim((string) $namespace, '\\');

            if ($trimmed !== '') {
                $namespaces[] = $trimmed;
            }
        }

        return $namespaces;
    }

    private function sourceCode(): string
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS),
        );

        $source = '';

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source .= (string) file_get_contents($file->getPathname());
            }
        }

        self::assertNotSame('', $source, 'src/ holds no PHP source; the guard has nothing to scan.');

        return $source;
    }
}
