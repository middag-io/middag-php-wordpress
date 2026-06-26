<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\EscapeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
#[CoversClass(EscapeSupport::class)]
final class EscapeSupportTest extends TestCase
{
    // ── WordPress-present path (delegates to the esc_* stubs) ─────────────────

    #[Test]
    public function htmlEscapesAngleBracketsAndQuotes(): void
    {
        $escaped = EscapeSupport::html('<b>"hi" & \'bye\'</b>');

        self::assertStringNotContainsString('<b>', $escaped);
        self::assertStringContainsString('&lt;b&gt;', $escaped);
        self::assertStringContainsString('&amp;', $escaped);
        self::assertStringContainsString('&quot;', $escaped);
    }

    #[Test]
    public function attrEscapesQuotesSoAttributeCannotBeBrokenOut(): void
    {
        $escaped = EscapeSupport::attr('"><script>alert(1)</script>');

        self::assertStringNotContainsString('"', $escaped);
        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&quot;', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
    }

    #[Test]
    public function urlPassesThroughAnHttpUrl(): void
    {
        self::assertSame('https://example.com/path', EscapeSupport::url('https://example.com/path'));
    }

    #[Test]
    public function urlRejectsAJavascriptScheme(): void
    {
        self::assertSame('', EscapeSupport::url('javascript:alert(1)'));
    }

    #[Test]
    public function urlHonorsACustomProtocolAllowlist(): void
    {
        self::assertSame('ftp://host/file', EscapeSupport::url('ftp://host/file', ['ftp']));
        self::assertSame('', EscapeSupport::url('https://host', ['ftp']));
    }

    // ── No-WordPress degrade path (the pure-PHP *Fallback bodies) ─────────────
    //
    // The suite always loads the WP stubs, so the public methods above only
    // exercise the WordPress branch. These call the private fallback bodies
    // directly — the actual code that runs off WordPress, where the escape
    // contract is the only line of defense.

    #[Test]
    public function htmlFallbackEscapesQuotesAndTagsOffWordPress(): void
    {
        $escaped = $this->htmlFallback('"><script>alert(1)</script>');

        self::assertStringNotContainsString('"', $escaped);
        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&quot;', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
    }

    #[Test]
    public function urlFallbackPassesHttpAndRelativeUrls(): void
    {
        self::assertSame('https://example.com/path', $this->urlFallback('https://example.com/path'));
        self::assertSame('/relative/page', $this->urlFallback('/relative/page'));
    }

    #[Test]
    public function urlFallbackRejectsDangerousSchemes(): void
    {
        self::assertSame('', $this->urlFallback('javascript:alert(1)'));
        self::assertSame('', $this->urlFallback('data:text/html,<script>'));
        self::assertSame('', $this->urlFallback('vbscript:msgbox(1)'));
    }

    #[Test]
    public function urlFallbackRejectsCaseVariantSchemes(): void
    {
        // Scheme matching must be case-insensitive — 'JavaScript:' is still XSS.
        self::assertSame('', $this->urlFallback('JavaScript:alert(1)'));
    }

    #[Test]
    public function urlFallbackRejectsProtocolRelativeUrls(): void
    {
        // '//evil.com' has no scheme but an absolute authority — open-redirect.
        self::assertSame('', $this->urlFallback('//evil.com/path'));
    }

    #[Test]
    public function urlFallbackHonorsACustomProtocolAllowlist(): void
    {
        self::assertSame('ftp://host/file', $this->urlFallback('ftp://host/file', ['ftp']));
        self::assertSame('', $this->urlFallback('https://host', ['ftp']));
    }

    private function htmlFallback(string $value): string
    {
        return (string) (new ReflectionMethod(EscapeSupport::class, 'htmlFallback'))->invoke(null, $value);
    }

    /**
     * @param array<int, string> $protocols
     */
    private function urlFallback(string $value, array $protocols = []): string
    {
        return (string) (new ReflectionMethod(EscapeSupport::class, 'urlFallback'))->invoke(null, $value, $protocols);
    }
}
