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
use Middag\WordPress\Support\OptionSupport;

/**
 * WordPress adapter for config resolution.
 *
 * Resolution hierarchy: env var → wp_options → default. The key prefixes are
 * configurable (defaults: `MIDDAGLIB_` for env vars, `middaglib_` for options)
 * — a neutral marker meaning "came from this library, no product prefix was
 * configured," carrying no brand of any consumer, including MIDDAG itself.
 * A real product wires its own prefixes explicitly in its composition root
 * (e.g. MIDDAG's own products inject `MIDDAG_`/`middag_` there); any other
 * consumer plugin may pass its own pair the same way. Env vars can be set via
 * wp-config.php putenv(), a .env file (phpdotenv), Docker ENV, or server
 * configuration (Apache/Nginx SetEnv).
 *
 * Public surface: the consumer's composition root registers it for the framework
 * `ConfigResolverInterface`, passing its own env/option prefixes.
 *
 * @api
 */
final readonly class WpConfigResolver implements ConfigResolverInterface
{
    public function __construct(
        private string $envPrefix = 'MIDDAGLIB_',
        private string $optionPrefix = 'middaglib_',
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
        $optVal = OptionSupport::get($this->optionPrefix . strtolower($fullKey));
        if (is_string($optVal) && $optVal !== '') {
            return $optVal;
        }

        return '';
    }
}
