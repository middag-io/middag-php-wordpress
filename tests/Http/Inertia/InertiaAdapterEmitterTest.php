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
use Middag\WordPress\Tests\Http\RecordingEmitter;
use Middag\WordPress\Tests\Http\TerminateSignal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Emitter-seam coverage: exercises the JSON / redirect / exit paths that were
 * previously untestable inline `header`/`echo`/`exit` (sendJson via an Inertia
 * request; location()'s 409 and redirect branches) through a RecordingEmitter.
 * TerminateSignal stands in for the production `exit`, so the terminating
 * branches are assertable in-process.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterEmitterTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'nonce-xyz'];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function inertiaRequestEmitsTheJsonEnvelopeAndTerminates(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->render('Dashboard', ['greeting' => 'hello']);
            self::fail('render() must terminate on an Inertia request');
        } catch (TerminateSignal) {
            // expected — the production exit stand-in
        }

        self::assertSame('application/json', $emitter->headers['Content-Type'] ?? null);
        self::assertSame('true', $emitter->headers['X-Inertia'] ?? null);
        self::assertSame('X-Inertia', $emitter->headers['Vary'] ?? null);

        $page = json_decode($emitter->body, true);
        self::assertSame('Dashboard', $page['component']);
        self::assertSame('hello', $page['props']['greeting']);
        self::assertSame('5.0.0', $page['version']);
        self::assertSame('/wp-admin/admin.php?page=middag', $page['url']);
    }

    #[Test]
    public function partialReloadFiltersPropsToTheRequestedKeys(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'] = 'Dashboard';
        $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'] = 'user';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->render('Dashboard', ['user' => 'Ada', 'stats' => [1, 2]]);
        } catch (TerminateSignal) {
            // expected
        }

        $page = json_decode($emitter->body, true);
        self::assertArrayHasKey('user', $page['props']);
        self::assertArrayNotHasKey('stats', $page['props'], 'partial reload must drop unrequested props');
    }

    #[Test]
    public function locationOnInertiaRequestEmits409WithLocationHeaderAndTerminates(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->location('https://example.test/next');
            self::fail('location() must terminate');
        } catch (TerminateSignal) {
            // expected
        }

        self::assertSame(409, $emitter->status);
        self::assertSame('https://example.test/next', $emitter->headers['X-Inertia-Location'] ?? null);
        self::assertNull($emitter->redirectedTo, 'the Inertia branch must not wp_redirect');
    }

    #[Test]
    public function locationOnNonInertiaRequestRedirectsAndTerminates(): void
    {
        unset($_SERVER['HTTP_X_INERTIA']);
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        try {
            $adapter->location('https://example.test/next');
            self::fail('location() must terminate');
        } catch (TerminateSignal) {
            // expected
        }

        self::assertSame('https://example.test/next', $emitter->redirectedTo);
        self::assertNull($emitter->status, 'the non-Inertia branch sets no 409 status');
    }

    #[Test]
    public function fullPageLoadWritesTheComponentNamespacedMountNodeThroughTheEmitter(): void
    {
        unset($_SERVER['HTTP_X_INERTIA']);
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        // Non-Inertia request: no termination, mount node written via the emitter.
        $adapter->render('Dashboard', []);

        self::assertStringContainsString('id="middag-app"', $emitter->body);
        self::assertStringContainsString('data-page=', $emitter->body);
    }
}
