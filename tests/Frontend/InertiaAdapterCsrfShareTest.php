<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Frontend;

use Middag\WordPress\Frontend\CsrfGuard;
use Middag\WordPress\Frontend\InertiaAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers the WP-03 contract that {@see InertiaAdapter} auto-shares the native
 * WordPress nonce to the SPA as the `csrfToken` prop, so the client can echo it
 * back through the `X-WP-Nonce` header that {@see CsrfGuard} verifies.
 *
 * Exercised through the private `resolveProps()` via reflection (mirroring
 * {@see InertiaAdapterVersionTest}) so the assertion stays on the prop pipeline
 * and avoids the `render()` exit/echo path.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterCsrfShareTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSharedProps();
        $GLOBALS['__wp_test_nonces'] = [CsrfGuard::NONCE_ACTION => 'spa-nonce'];
    }

    protected function tearDown(): void
    {
        $this->resetSharedProps();
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function autoSharesTheWpNonceAsCsrfToken(): void
    {
        $props = $this->resolveProps([]);

        self::assertSame('spa-nonce', $props['csrfToken']);
    }

    #[Test]
    public function anExplicitCsrfTokenPropOverridesTheAutoSharedNonce(): void
    {
        $props = $this->resolveProps(['csrfToken' => 'caller-supplied']);

        self::assertSame('caller-supplied', $props['csrfToken']);
    }

    #[Test]
    public function anExplicitlySharedCsrfTokenOverridesTheAutoSharedNonce(): void
    {
        InertiaAdapter::share('csrfToken', 'shared-supplied');

        $props = $this->resolveProps([]);

        self::assertSame('shared-supplied', $props['csrfToken']);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resolveProps(array $props): array
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'resolveProps');

        return (array) $method->invoke(null, $props);
    }

    private function resetSharedProps(): void
    {
        InertiaAdapter::reset();
    }
}
