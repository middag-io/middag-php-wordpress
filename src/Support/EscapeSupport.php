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
 * Thin wrapper over WordPress's output escapers.
 *
 * The adapter escapes values emitted at WordPress output boundaries (HTML text,
 * HTML attributes, URLs) through this seam instead of calling
 * `esc_html`/`esc_attr`/`esc_url` directly, so the platform coupling lives in
 * one place. When the WordPress function is absent (CLI / cron / boot before the
 * formatting API loads) each method degrades to a conservative pure-PHP escaper
 * — never a blind passthrough — so output is still safe off the WordPress
 * runtime. The degrade bodies live in dedicated `*Fallback` methods so the
 * no-WordPress security path is unit-testable on its own.
 *
 * Escaping is for *output* boundaries; sanitizing inbound data is
 * {@see SanitizeSupport}. Escape exactly once at the boundary — do not re-escape
 * a value that has already been escaped (e.g. a JSON payload already passed
 * through {@see attr()}).
 *
 * @internal
 */
final class EscapeSupport
{
    /**
     * Escape a value for safe output as HTML text. Mirrors `esc_html()`.
     */
    public static function html(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : self::htmlFallback($value);
    }

    /**
     * Escape a value for safe output inside an HTML attribute. Mirrors
     * `esc_attr()`.
     */
    public static function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : self::htmlFallback($value);
    }

    /**
     * Escape a URL for safe output (href/src). Mirrors `esc_url()`; degrades to
     * a filter + scheme allowlist, returning '' for a disallowed scheme.
     *
     * @param array<int, string> $protocols allowed URL protocols (defaults to http/https/mailto)
     */
    public static function url(string $value, array $protocols = []): string
    {
        if (function_exists('esc_url')) {
            return $protocols === [] ? esc_url($value) : esc_url($value, $protocols);
        }

        return self::urlFallback($value, $protocols);
    }

    /**
     * Pure-PHP HTML escape used when WordPress's `esc_html`/`esc_attr` are absent
     * (CLI / cron / early boot). Conservative quote-and-tag escaping via
     * `htmlspecialchars`. Kept separate so the no-WordPress path is testable.
     */
    private static function htmlFallback(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Pure-PHP URL escape used when `esc_url` is absent: a sanitize filter plus a
     * scheme allowlist. Absolute URLs must use an allowlisted scheme; a
     * protocol-relative URL (`//host`) is rejected outright (an open-redirect
     * vector real `esc_url()` neutralizes); scheme-less relative paths pass.
     *
     * @param array<int, string> $protocols
     */
    private static function urlFallback(string $value, array $protocols = []): string
    {
        $filtered = filter_var($value, FILTER_SANITIZE_URL);
        if ($filtered === false || $filtered === '') {
            return '';
        }

        // Reject protocol-relative URLs ('//evil.com'): no scheme yet an absolute
        // authority — an open-redirect/XSS-adjacent vector. Real esc_url() would
        // resolve these against the site scheme; off WordPress, drop them.
        if (str_starts_with($filtered, '//')) {
            return '';
        }

        $allowed = $protocols === [] ? ['http', 'https', 'mailto'] : $protocols;
        $scheme = parse_url($filtered, PHP_URL_SCHEME);

        // Relative URLs (no scheme) are allowed through; absolute URLs must use
        // an allowlisted scheme.
        if (is_string($scheme) && !in_array(strtolower($scheme), $allowed, true)) {
            return '';
        }

        return $filtered;
    }
}
