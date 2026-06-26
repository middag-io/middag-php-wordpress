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

use Middag\WordPress\Support\SanitizeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
#[CoversClass(SanitizeSupport::class)]
final class SanitizeSupportTest extends TestCase
{
    // ── WordPress-present path (delegates to the sanitize_* stubs) ────────────

    #[Test]
    public function textStripsMarkupAndCollapsesWhitespace(): void
    {
        $malicious = "  <script>alert('xss')</script>Hello\n\tWorld  ";

        $clean = SanitizeSupport::text($malicious);

        self::assertStringNotContainsString('<script>', $clean);
        self::assertStringNotContainsString('</script>', $clean);
        self::assertSame("alert('xss')Hello World", $clean);
    }

    #[Test]
    public function keyLowercasesAndStripsDisallowedCharacters(): void
    {
        self::assertSame('my_key-1', SanitizeSupport::key('My_Key-1!@#'));
        self::assertSame('abc', SanitizeSupport::key('  A B C  '));
    }

    #[Test]
    public function emailKeepsValidAddressAndRejectsGarbage(): void
    {
        self::assertSame('user@example.com', SanitizeSupport::email('user@example.com'));
        self::assertSame('', SanitizeSupport::email('not-an-email'));
    }

    #[Test]
    public function emailStripsDisallowedCharactersFromAnOtherwiseValidAddress(): void
    {
        self::assertSame('user@example.com', SanitizeSupport::email('user<>@example.com'));
    }

    #[Test]
    public function textareaStripsTagsButPreservesNewlines(): void
    {
        $input = "line one<script>bad</script>\nline two";

        $clean = SanitizeSupport::textarea($input);

        self::assertStringNotContainsString('<script>', $clean);
        self::assertStringContainsString("\n", $clean);
        self::assertSame("line onebad\nline two", $clean);
    }

    #[Test]
    public function ksesPostRemovesDisallowedTagsAndKeepsAllowedOnes(): void
    {
        $html = '<strong>keep</strong><script>alert(1)</script><em>also</em>';

        $clean = SanitizeSupport::ksesPost($html);

        self::assertStringContainsString('<strong>keep</strong>', $clean);
        self::assertStringContainsString('<em>also</em>', $clean);
        self::assertStringNotContainsString('<script>', $clean);
        self::assertStringNotContainsString('</script>', $clean);
    }

    #[Test]
    public function ksesStripsToTheProvidedAllowlist(): void
    {
        $html = '<b>bold</b><i>italic</i><script>x</script>';

        $clean = SanitizeSupport::kses($html, ['b' => []]);

        self::assertStringContainsString('<b>bold</b>', $clean);
        self::assertStringNotContainsString('<i>', $clean);
        self::assertStringNotContainsString('<script>', $clean);
    }

    // ── No-WordPress degrade path (the pure-PHP *Fallback bodies) ─────────────
    //
    // The suite always loads the WP stubs, so the public methods above can only
    // exercise the WordPress branch. These call the private fallback bodies
    // directly — the actual code that runs off WordPress (CLI / cron / early
    // boot), where the sanitize contract is the only line of defense.

    #[Test]
    public function textFallbackStripsMarkupOffWordPress(): void
    {
        // strip_tags removes the tags; inert text content (the 'x') may remain,
        // which is harmless once the executable markup is gone.
        $clean = $this->fallback('textFallback', '<script>x</script>plain');
        self::assertStringNotContainsString('<', $clean);
        self::assertStringNotContainsString('>', $clean);

        self::assertSame('a b c', $this->fallback('textFallback', "  a\n b\t c  "));
    }

    #[Test]
    public function keyFallbackLowercasesAndFiltersOffWordPress(): void
    {
        self::assertSame('abcdef', $this->fallback('keyFallback', 'Abc!def'));
        self::assertSame('my_key-1', $this->fallback('keyFallback', 'My_Key-1!@#'));
    }

    #[Test]
    public function emailFallbackReturnsEmptyForGarbageOffWordPress(): void
    {
        self::assertSame('', $this->fallback('emailFallback', 'not-an-email'));
        self::assertSame('user@example.com', $this->fallback('emailFallback', 'user@example.com'));
    }

    #[Test]
    public function textareaFallbackStripsTagsButKeepsNewlinesOffWordPress(): void
    {
        $clean = $this->fallback('textareaFallback', "line one<script>bad</script>\nline two");

        self::assertStringNotContainsString('<script>', $clean);
        self::assertStringContainsString("\n", $clean);
    }

    #[Test]
    public function ksesFallbackStripsEveryTagOffWordPress(): void
    {
        // No allowlist is available off WordPress, so the safe degrade strips all.
        self::assertSame('keepalsox', $this->fallback('ksesFallback', '<strong>keep</strong><em>also</em><script>x</script>'));
    }

    /**
     * Invoke a private static fallback body by name (the no-WordPress path).
     */
    private function fallback(string $method, string $input): string
    {
        return (string) (new ReflectionMethod(SanitizeSupport::class, $method))->invoke(null, $input);
    }
}
