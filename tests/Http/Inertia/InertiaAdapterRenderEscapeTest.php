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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WP-04 output-boundary coverage: on a first (non-Inertia) visit the adapter
 * writes the framework's page payload into the `data-page` attribute of the
 * component-namespaced mount node (`middag-app` for the `middag` component),
 * escaped exactly once through the Escape seam — no double-escaping of the
 * framework's internal JSON, no live `<script>` breakout.
 *
 * Driven through the public {@see InertiaAdapter::render()} full-visit path via a
 * {@see RecordingEmitter} so the assertion exercises the real HTML shell the
 * framework wire hands back, not a private helper.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterRenderEscapeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'nonce-xyz'];
        unset($_SERVER['HTTP_X_INERTIA']);
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function renderEmitsThePagePayloadEscapedInTheDataPageAttribute(): void
    {
        $html = $this->render('Dashboard', ['title' => '<script>alert(1)</script>']);

        // The mount node is present and the attribute carries the payload.
        self::assertStringStartsWith('<div id="middag-app" data-page="', $html);
        self::assertStringEndsWith('"></div>', $html);

        // No raw closing-attribute quote leaked from the JSON; no live <script>.
        self::assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function dataPageAttributeIsEscapedExactlyOnce(): void
    {
        $html = $this->render('X', ['q' => 'a&b"c']);

        // A single escaping layer never produces the double-escaped '&amp;amp;'.
        self::assertStringContainsString('data-page="', $html);
        self::assertStringNotContainsString('&amp;amp;', $html, 'data-page must be escaped exactly once');
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(string $component, array $props): string
    {
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        $adapter->render($component, $props);

        return $emitter->body;
    }
}
