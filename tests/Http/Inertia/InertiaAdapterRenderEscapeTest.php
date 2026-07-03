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
use Middag\WordPress\Support\EscapeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Throwable;

/**
 * WP-04 output-boundary coverage: {@see InertiaAdapter::renderHtml()} emits the
 * page payload into the `data-page` attribute through the {@see EscapeSupport}
 * seam, escaped exactly once (no double-escaping of the framework's internal
 * JSON).
 *
 * Exercised through the private `renderHtml()` via reflection (mirroring
 * {@see InertiaAdapterCsrfShareTest}) to avoid the `render()` exit/echo path.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterRenderEscapeTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSharedProps();
    }

    protected function tearDown(): void
    {
        $this->resetSharedProps();
    }

    #[Test]
    public function renderHtmlEscapesThePagePayloadInTheDataPageAttribute(): void
    {
        $page = [
            'component' => 'Dashboard',
            'props' => ['title' => '<script>alert(1)</script>'],
            'url' => '/wp-admin/admin.php?page=middag',
            'version' => '5.0.0',
        ];

        $html = $this->renderHtml($page);

        // The mount node is present and the attribute carries the payload.
        self::assertStringStartsWith('<div id="middag-app" data-page="', $html);
        self::assertStringEndsWith('"></div>', $html);

        // No raw closing-attribute quote leaked from the JSON; no live <script>.
        self::assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function dataPageAttributeIsEscapedExactlyOnce(): void
    {
        $page = [
            'component' => 'X',
            'props' => ['q' => 'a&b"c'],
            'url' => '/x',
            'version' => '1',
        ];

        $html = $this->renderHtml($page);

        // Reconstruct the expected single-layer escaping: JSON-encode (as the
        // adapter does), then escape once via the seam. If the adapter
        // double-escaped, the '&' would appear as '&amp;amp;' instead.
        $json = (string) wp_json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $expectedAttr = EscapeSupport::attr($json);

        self::assertSame('<div id="middag-app" data-page="' . $expectedAttr . '"></div>', $html);
        self::assertStringNotContainsString('&amp;amp;', $html, 'data-page must be escaped exactly once');
    }

    /**
     * @param array<string, mixed> $page
     */
    private function renderHtml(array $page): string
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'renderHtml');

        ob_start();

        try {
            $method->invoke(null, $page);

            return ob_get_clean();
        } catch (Throwable $throwable) {
            ob_end_clean();

            throw $throwable;
        }
    }

    private function resetSharedProps(): void
    {
        InertiaAdapter::reset();
    }
}
