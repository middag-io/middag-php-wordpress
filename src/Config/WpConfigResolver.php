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
 * Resolution hierarchy: env var → wp_options → default. The key prefixes are
 * configurable (defaults: `MIDDAG_` for env vars, `middag_` for options); a
 * consumer plugin may pass its own. Env vars can be set via wp-config.php
 * putenv(), a .env file (phpdotenv), Docker ENV, or server configuration
 * (Apache/Nginx SetEnv).
 */
final readonly class WpConfigResolver implements ConfigResolverInterface
{
    public function __construct(
        private string $envPrefix = 'MIDDAG_',
        private string $optionPrefix = 'middag_',
    ) {}

    public function get(string $key, ?string $entitySlug = null, string $default = ''): string
    {
        // Per-entity (4-part): try <envPrefix>PROVIDER_KEY_SLUG first
        if ($entitySlug !== null) {
            $result = $this->resolve($key . '_' . $entitySlug);
            if ($result !== '') {
                return $result;
            }
        }

        // Global (3-part): <envPrefix>PROVIDER_KEY — also the fallback for single-entity setups
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
        // 1. Env var — highest priority
        $envVal = getenv($this->envPrefix . strtoupper($fullKey));
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }

        // 2. wp_options
        $optVal = get_option($this->optionPrefix . strtolower($fullKey));
        if (is_string($optVal) && $optVal !== '') {
            return $optVal;
        }

        return '';
    }
}
