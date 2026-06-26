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
 * Thin wrapper over WordPress's mail seam.
 *
 * Wraps `wp_mail` and the `wp_mail_from`/`wp_mail_from_name` filters (routed
 * through {@see HookSupport}) so the sender never touches the mail globals
 * directly. Returns false when the mail API is unavailable.
 *
 * @internal
 */
final class MailSupport
{
    /**
     * Send an email via wp_mail.
     *
     * @param array<int, string>|string $to
     * @param array<int, string>|string $headers
     * @param array<int, string>        $attachments
     */
    public static function send(
        array|string $to,
        string $subject,
        string $message,
        array|string $headers = '',
        array $attachments = [],
    ): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Register the "From" name and email for subsequent sends via WordPress
     * filters. The filters persist until removed by WordPress at request end.
     */
    public static function setFrom(string $email, string $name): void
    {
        HookSupport::addFilter('wp_mail_from', static fn (): string => $email);
        HookSupport::addFilter('wp_mail_from_name', static fn (): string => $name);
    }
}
