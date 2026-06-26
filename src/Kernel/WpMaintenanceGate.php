<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Kernel;

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;

/**
 * WordPress maintenance gate.
 *
 * Reports whether WordPress is mid-upgrade / in maintenance so the kernel stands
 * down instead of booting modules/routes against a half-upgraded core.
 *
 * Probes, in order:
 *   - `wp_is_maintenance_mode()` (WP 6.5+ — honours the drop-in maintenance flag);
 *   - the legacy `.maintenance` drop-in file in ABSPATH (written by the core
 *     updater; defines `$upgrading` as a UNIX timestamp and is considered active
 *     for 10 minutes after it was written, matching wp_maintenance()).
 */
final class WpMaintenanceGate implements MaintenanceGateInterface
{
    /** Window (seconds) the legacy `.maintenance` flag is considered active. */
    private const MAINTENANCE_WINDOW = 600;

    public function isUnderMaintenance(): bool
    {
        if (function_exists('wp_is_maintenance_mode') && wp_is_maintenance_mode()) {
            return true;
        }

        return $this->legacyMaintenanceFileActive();
    }

    /**
     * Replicate core's wp_maintenance() check for the `.maintenance` drop-in:
     * active when the file exists and its `$upgrading` timestamp is within the
     * last 10 minutes (stale flags are ignored, as core does).
     *
     * The timestamp is parsed out of the file source rather than `include`d, so
     * no arbitrary PHP from the drop-in is executed.
     */
    private function legacyMaintenanceFileActive(): bool
    {
        if (!defined('ABSPATH')) {
            return false;
        }

        $file = ABSPATH . '.maintenance';
        if (!is_file($file)) {
            return false;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return false;
        }

        // Core writes `<?php $upgrading = <timestamp>;` — extract the integer.
        if (preg_match('/\$upgrading\s*=\s*(\d+)\s*;/', $contents, $matches) !== 1) {
            return false;
        }

        $upgrading = (int) $matches[1];

        return $upgrading > 0 && (time() - $upgrading) < self::MAINTENANCE_WINDOW;
    }
}
