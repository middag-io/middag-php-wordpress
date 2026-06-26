<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Config;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;

/**
 * WordPress adapter for config resolution.
 *
 * Resolution hierarchy: env var (MDGA_*) → wp_options (mdga_*) → default.
 * Env vars can be set via wp-config.php putenv(), .env file (phpdotenv),
 * Docker ENV, or server configuration (Apache/Nginx SetEnv).
 *
 * @see REF-102-06
 */
final readonly class WpConfigResolver implements ConfigResolverInterface
{
    public function get(string $key, ?string $entitySlug = null, string $default = ''): string
    {
        // Per-entity (4-part): try MDGA_PROVIDER_KEY_SLUG first
        if ($entitySlug !== null) {
            $result = $this->resolve($key . '_' . $entitySlug);
            if ($result !== '') {
                return $result;
            }
        }

        // Global (3-part): MDGA_PROVIDER_KEY — also serves as fallback for single-entity setups
        $result = $this->resolve($key);
        if ($result !== '') {
            return $result;
        }

        return $default;
    }

    public function has(string $key, ?string $entitySlug = null): bool
    {
        return $this->get($key, $entitySlug) !== '';
    }

    private function resolve(string $fullKey): string
    {
        // 1. Env var (MDGA_*) — highest priority
        $envVal = getenv('MDGA_' . strtoupper($fullKey));
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }

        // 2. wp_options (mdga_*)
        $optVal = get_option('mdga_' . strtolower($fullKey));
        if (is_string($optVal) && $optVal !== '') {
            return $optVal;
        }

        return '';
    }
}
