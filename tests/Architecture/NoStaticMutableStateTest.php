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
use ReflectionClass;
use ReflectionProperty;

/**
 * Architecture guard (O5-LIB-03 / O5-LIB-07): no source class may declare a
 * static property. PHP has no readonly static, so a static property is
 * process-wide mutable state that collides between two plugins running in the
 * same WordPress request (the exact bug LIB-03 removed — InertiaAdapter shared
 * props, LogSupport logger, AuthMiddleware issuer). Per-request state must be an
 * injected instance dependency instead.
 *
 * Constants and static methods are fine; only static *properties* are banned.
 *
 * @internal
 */
#[CoversNothing]
final class NoStaticMutableStateTest extends TestCase
{
    #[Test]
    #[DataProvider('sourceClassProvider')]
    public function sourceClassDeclaresNoStaticProperty(string $fqcn): void
    {
        $reflection = new ReflectionClass($fqcn);

        $offenders = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            // Only flag properties DECLARED here, not inherited from a base.
            if ($property->getDeclaringClass()->getName() === $fqcn) {
                $offenders[] = '$' . $property->getName();
            }
        }

        self::assertSame([], $offenders, sprintf(
            '%s declares mutable static state (%s) — process-wide statics collide between plugins in one request; inject an instance dependency instead.',
            $fqcn,
            implode(', ', $offenders),
        ));
    }

    /**
     * Every instantiable/trait/enum source type under src/ (PSR-4
     * Middag\WordPress\ => src/). Interfaces are skipped (they cannot hold
     * properties).
     *
     * @return array<string, array{string}>
     */
    public static function sourceClassProvider(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $classes = [];

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
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $fqcn = 'Middag\WordPress\\' . str_replace('/', '\\', substr($relative, 0, -4));

            if (class_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                $classes[$fqcn] = [$fqcn];
            }
        }

        ksort($classes);

        return $classes;
    }
}
