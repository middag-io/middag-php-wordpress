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

use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Kernel\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterVersionTest extends TestCase
{
    protected function setUp(): void
    {
        HostContext::reset();
    }

    protected function tearDown(): void
    {
        HostContext::reset();
    }

    #[Test]
    public function usesTheConfiguredHostAssetVersion(): void
    {
        HostContext::set(new WpComponentContext('my-plugin', '9.9.9'));

        self::assertSame('9.9.9', $this->resolveVersion());
    }

    #[Test]
    public function fallsBackToSafeDefaultWhenNoHostConfigured(): void
    {
        self::assertSame('5.0.0', $this->resolveVersion());
    }

    private function resolveVersion(): string
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'getVersion');

        return (string) $method->invoke(null);
    }
}
