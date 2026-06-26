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

use Middag\WordPress\Database\WpdbConnectionAdapter;
use wpdb;

/**
 * Safe access to the WordPress superglobals the adapter depends on.
 *
 * Normalizes how the bootstrap resolves `$GLOBALS['wpdb']`: a single typed
 * accessor that returns null when the global is missing or not a wpdb instance,
 * instead of scattering `isset($GLOBALS['wpdb'])` checks across the container.
 *
 * Note: {@see WpdbConnectionAdapter} keeps its direct
 * `$wpdb` use — it is the framework's database boundary. This seam only covers
 * resolving the global before it is handed to that adapter.
 *
 * @internal
 */
final class GlobalsSupport
{
    /**
     * The active `$wpdb` instance, or null when it has not been initialized.
     */
    public static function wpdb(): ?wpdb
    {
        $candidate = $GLOBALS['wpdb'] ?? null;

        return $candidate instanceof wpdb ? $candidate : null;
    }
}
