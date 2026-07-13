<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Inertia;

use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The rendered page version is sourced from the injected component context, so
 * each host plugin cache-busts its own assets independently.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterVersionTest extends TestCase
{
    #[Test]
    public function usesTheInjectedComponentAssetVersion(): void
    {
        $adapter = new InertiaAdapter(new WpComponentContext('my-plugin', '9.9.9'));

        self::assertSame('9.9.9', $this->resolveVersion($adapter));
    }

    #[Test]
    public function eachComponentReportsItsOwnVersion(): void
    {
        $a = new InertiaAdapter(new WpComponentContext('plugin-a', '1.0.0'));
        $b = new InertiaAdapter(new WpComponentContext('plugin-b', '2.0.0'));

        self::assertSame('1.0.0', $this->resolveVersion($a));
        self::assertSame('2.0.0', $this->resolveVersion($b));
    }

    private function resolveVersion(InertiaAdapter $adapter): string
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'getVersion');

        return (string) $method->invoke($adapter);
    }
}
