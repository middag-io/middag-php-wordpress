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

use Middag\Framework\Http\Inertia\InertiaAdapter as FrameworkInertia;
use Middag\Framework\Http\Inertia\InertiaResponse;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Tests\Http\RecordingEmitter;
use Middag\WordPress\Tests\Http\TerminateSignal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * P0-LIB-B: proves the WordPress adapter now delegates the Inertia v3 wire to
 * the framework instead of hand-rolling a v1-subset. Exercising deferred props,
 * merge props and asset-version skew through the public {@see
 * InertiaAdapter::render()} path shows the framework
 * {@see InertiaResponse} is the single source of
 * the protocol — behaviour the previous standalone adapter did not have.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterWireTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'nonce-xyz'];
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        unset(
            $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'],
            $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'],
            $_SERVER['HTTP_X_INERTIA_VERSION'],
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function deferredPropsAreAnnouncedAndWithheldOnTheInitialVisit(): void
    {
        $page = $this->renderToPage('Dashboard', [
            'stats' => FrameworkInertia::defer(static fn (): array => [1, 2, 3], 'metrics'),
        ]);

        // Absent from the initial payload, announced for a follow-up partial.
        self::assertArrayNotHasKey('stats', $page['props']);
        self::assertSame(['metrics' => ['stats']], $page['deferredProps'] ?? null);
    }

    #[Test]
    public function mergePropsAreResolvedAndFlaggedForClientSideMerge(): void
    {
        $page = $this->renderToPage('Feed', [
            'items' => FrameworkInertia::merge([1, 2]),
        ]);

        self::assertSame([1, 2], $page['props']['items']);
        self::assertSame(['items'], $page['mergeProps'] ?? null);
    }

    #[Test]
    public function assetVersionSkewOnAGetVisitReturns409WithLocation(): void
    {
        $_SERVER['HTTP_X_INERTIA_VERSION'] = 'stale-hash';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->render('Dashboard', ['greeting' => 'hello']);
        } catch (TerminateSignal) {
            // expected — the Inertia branch terminates
        }

        self::assertSame(409, $emitter->status);
        self::assertArrayHasKey('X-Inertia-Location', $emitter->headers);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function renderToPage(string $component, array $props): array
    {
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->render($component, $props);
        } catch (TerminateSignal) {
            // expected — the Inertia (JSON) branch terminates
        }

        return (array) json_decode($emitter->body, true);
    }
}
