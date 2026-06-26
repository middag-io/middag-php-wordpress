<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Support;

/**
 * Thin wrapper over WordPress's inbound sanitizers.
 *
 * The adapter cleans request-borne scalars and HTML through this seam instead
 * of calling `sanitize_text_field`/`sanitize_key`/`sanitize_email`/
 * `sanitize_textarea_field`/`wp_kses_post`/`wp_kses` directly, so the platform
 * coupling lives in one place. When the WordPress function is absent (CLI / cron
 * / boot before the formatting API loads) each method degrades to a conservative
 * pure-PHP equivalent — never a blind passthrough — so untrusted input is still
 * neutralized off the WordPress runtime. The degrade bodies live in dedicated
 * `*Fallback` methods so the no-WordPress security path is unit-testable on its
 * own.
 *
 * Sanitizing is for *inbound* data; escaping output is {@see EscapeSupport}.
 *
 * @internal
 */
final class SanitizeSupport
{
    /**
     * Sanitize a single-line text scalar: strips tags, removes line breaks and
     * extra whitespace. Mirrors `sanitize_text_field()`.
     */
    public static function text(string $value): string
    {
        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : self::textFallback($value);
    }

    /**
     * Sanitize a key (slug/identifier): lowercase, keeping only alphanumerics,
     * dashes and underscores. Mirrors `sanitize_key()`.
     */
    public static function key(string $value): string
    {
        return function_exists('sanitize_key') ? sanitize_key($value) : self::keyFallback($value);
    }

    /**
     * Sanitize an email address. Mirrors `sanitize_email()`; degrades to PHP's
     * email-sanitizing filter, returning '' when the result is unusable.
     */
    public static function email(string $value): string
    {
        return function_exists('sanitize_email') ? sanitize_email($value) : self::emailFallback($value);
    }

    /**
     * Sanitize a multi-line text scalar, preserving newlines. Mirrors
     * `sanitize_textarea_field()`.
     */
    public static function textarea(string $value): string
    {
        return function_exists('sanitize_textarea_field') ? sanitize_textarea_field($value) : self::textareaFallback($value);
    }

    /**
     * Sanitize an HTML fragment against the WordPress post context allowlist.
     * Mirrors `wp_kses_post()`; degrades to stripping all tags (safest fallback)
     * when the kses API is unavailable.
     */
    public static function ksesPost(string $value): string
    {
        return function_exists('wp_kses_post') ? wp_kses_post($value) : self::ksesFallback($value);
    }

    /**
     * Sanitize an HTML fragment against an explicit allowlist of elements and
     * attributes. Mirrors `wp_kses()`; degrades to stripping all tags (safest
     * fallback) when the kses API is unavailable.
     *
     * @param array<string, mixed>|string $allowedHtml      allowed elements/attributes, or a context name
     * @param array<int, string>          $allowedProtocols allowed URL protocols
     */
    public static function kses(string $value, array|string $allowedHtml, array $allowedProtocols = []): string
    {
        return function_exists('wp_kses') ? wp_kses($value, $allowedHtml, $allowedProtocols) : self::ksesFallback($value);
    }

    /**
     * Pure-PHP single-line text sanitize used when `sanitize_text_field` is
     * absent: strip tags, collapse all whitespace (incl. newlines), trim.
     */
    private static function textFallback(string $value): string
    {
        $stripped = strip_tags($value);
        $collapsed = preg_replace('/[\r\n\t ]+/', ' ', $stripped);

        return trim($collapsed ?? $stripped);
    }

    /**
     * Pure-PHP key sanitize used when `sanitize_key` is absent: lowercase,
     * keep only [a-z0-9_-].
     */
    private static function keyFallback(string $value): string
    {
        $lowered = strtolower($value);

        return (string) preg_replace('/[^a-z0-9_\-]/', '', $lowered);
    }

    /**
     * Pure-PHP email sanitize used when `sanitize_email` is absent: filter then
     * validate, returning '' when the result is not a usable address.
     */
    private static function emailFallback(string $value): string
    {
        $filtered = filter_var($value, FILTER_SANITIZE_EMAIL);
        if ($filtered === false) {
            return '';
        }

        return filter_var($filtered, FILTER_VALIDATE_EMAIL) === false ? '' : $filtered;
    }

    /**
     * Pure-PHP multi-line text sanitize used when `sanitize_textarea_field` is
     * absent: strip tags, collapse spaces/tabs (keep newlines), trim.
     */
    private static function textareaFallback(string $value): string
    {
        $stripped = strip_tags($value);
        $collapsed = preg_replace('/[ \t]+/', ' ', $stripped);

        return trim($collapsed ?? $stripped);
    }

    /**
     * Pure-PHP HTML sanitize used when the kses API is absent: strip every tag
     * (the safest degrade — no allowlist available off WordPress).
     */
    private static function ksesFallback(string $value): string
    {
        return strip_tags($value);
    }
}
