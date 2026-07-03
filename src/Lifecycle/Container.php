<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Lifecycle;

use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\WordPress\Support\GlobalsSupport;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use wpdb;

/**
 * Static container facade for the WordPress frontend layer.
 *
 * Mirrors the framework/moodle DI pattern but exposes a static accessor that
 * the admin renderer can reach from WordPress lifecycle hooks (no constructor
 * injection available inside `add_menu_page` callbacks). Production code
 * compiles the container once at boot via a `BootstrapInterface`; tests reset
 * it between cases.
 *
 * @api
 */
final class Container
{
    private static ?ContainerBuilder $builder = null;

    private static bool $compiled = false;

    public static function builder(): ContainerBuilder
    {
        if (!self::$builder instanceof ContainerBuilder) {
            self::$builder = new ContainerBuilder();
        }

        return self::$builder;
    }

    /**
     * Compile the container against the provided bootstrap implementation.
     *
     * Callers must provide a concrete bootstrap (e.g. `WpBootstrap`).
     * Idempotent: subsequent calls become no-ops once compiled.
     */
    public static function compile(BootstrapInterface $bootstrap): void
    {
        if (self::$compiled) {
            return;
        }

        $builder = self::builder();
        $bootstrap->configure($builder);
        $builder->compile();
        self::$compiled = true;

        // Inject real WordPress globals into synthetic slots when available
        $wpdb = GlobalsSupport::wpdb();
        if ($wpdb instanceof wpdb) {
            $builder->set('wordpress.wpdb', $wpdb);
        }
    }

    public static function get(string $id): mixed
    {
        if (!self::$compiled) {
            throw new RuntimeException(sprintf('Container not compiled; cannot resolve "%s".', $id));
        }

        return self::builder()->get($id);
    }

    public static function has(string $id): bool
    {
        return self::builder()->has($id);
    }

    public static function isCompiled(): bool
    {
        return self::$compiled;
    }

    /**
     * Reset container state (for testing only).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$builder = null;
        self::$compiled = false;
    }
}
