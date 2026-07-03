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
 * Boundary seam over `wp_upload_dir()`. Empty strings outside a WP runtime.
 *
 * @api
 */
final class UploadSupport
{
    /**
     * Absolute filesystem path to the uploads base directory.
     */
    public static function baseDir(): string
    {
        return (string) (self::info()['basedir'] ?? '');
    }

    /**
     * Public URL of the uploads base directory.
     */
    public static function baseUrl(): string
    {
        return (string) (self::info()['baseurl'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private static function info(): array
    {
        if (!\function_exists('wp_upload_dir')) {
            return [];
        }

        return wp_upload_dir();
    }
}
