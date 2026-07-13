<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Controller;

use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use Middag\WordPress\Http\Controller\AbstractWpRestController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * WP-04 inbound-boundary coverage: {@see AbstractWpRestController}'s sanitized
 * scalar readers route request input through the Sanitize seam before the
 * controller sees it (delegation proven via the behavioral WP stubs).
 *
 * @internal
 */
#[CoversClass(AbstractWpRestController::class)]
final class AbstractWpRestControllerSanitizeTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        // Concrete subclass exposing the protected sanitize helpers under test.
        $this->controller = new class(new WpSessionAuthenticator()) extends AbstractWpRestController {
            public function registerRoutes(string $namespace): void {}

            public function text(WP_REST_Request $r, string $k, string $d = ''): string
            {
                return $this->sanitizedText($r, $k, $d);
            }

            public function key(WP_REST_Request $r, string $k, string $d = ''): string
            {
                return $this->sanitizedKey($r, $k, $d);
            }

            public function email(WP_REST_Request $r, string $k, string $d = ''): string
            {
                return $this->sanitizedEmail($r, $k, $d);
            }

            public function textarea(WP_REST_Request $r, string $k, string $d = ''): string
            {
                return $this->sanitizedTextarea($r, $k, $d);
            }

            public function html(WP_REST_Request $r, string $k, string $d = ''): string
            {
                return $this->sanitizedHtml($r, $k, $d);
            }
        };
    }

    #[Test]
    public function sanitizedTextStripsMarkupFromInboundScalar(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('name', '<script>steal()</script>Ada');

        $clean = $this->controller->text($request, 'name');

        self::assertStringNotContainsString('<script>', $clean);
        self::assertSame('steal()Ada', $clean);
    }

    #[Test]
    public function sanitizedKeyNormalizesAnInboundSlug(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('slug', 'My Post!!');

        self::assertSame('mypost', $this->controller->key($request, 'slug'));
    }

    #[Test]
    public function sanitizedEmailReturnsCleanAddressOrDefault(): void
    {
        $valid = new WP_REST_Request();
        $valid->set_param('email', 'user@example.com');
        self::assertSame('user@example.com', $this->controller->email($valid, 'email'));

        $invalid = new WP_REST_Request();
        $invalid->set_param('email', 'nope');
        self::assertSame('fallback@x.test', $this->controller->email($invalid, 'email', 'fallback@x.test'));
    }

    #[Test]
    public function sanitizedTextareaPreservesNewlinesWhileStrippingTags(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('body', "para one<script>x</script>\npara two");

        $clean = $this->controller->textarea($request, 'body');

        self::assertStringNotContainsString('<script>', $clean);
        self::assertStringContainsString("\n", $clean);
    }

    #[Test]
    public function sanitizedHtmlFiltersAgainstThePostAllowlist(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('content', '<strong>ok</strong><script>bad</script>');

        $clean = $this->controller->html($request, 'content');

        self::assertStringContainsString('<strong>ok</strong>', $clean);
        self::assertStringNotContainsString('<script>', $clean);
    }

    #[Test]
    public function missingScalarReturnsTheProvidedDefault(): void
    {
        $request = new WP_REST_Request();

        self::assertSame('def', $this->controller->text($request, 'absent', 'def'));
        self::assertSame('', $this->controller->key($request, 'absent'));
    }

    #[Test]
    public function nonStringParamReturnsTheDefaultWithoutSanitizing(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('n', ['array', 'value']);

        self::assertSame('fallback', $this->controller->text($request, 'n', 'fallback'));
    }
}
