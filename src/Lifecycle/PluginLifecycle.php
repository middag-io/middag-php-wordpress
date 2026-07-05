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

use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Support\LifecycleSupport;

/**
 * Plugin activation/deactivation registrar.
 *
 * Wires the WordPress plugin lifecycle to the adapter's own setup/teardown,
 * routing every native call through {@see LifecycleSupport} so the platform
 * coupling stays in the Support seam. On deactivation it calls
 * {@see CronRegistrar::unregister()} so scheduled WP-Cron events do not survive
 * the plugin being switched off.
 *
 * Optional activation/deactivation callbacks let a consumer run extra
 * setup/teardown (e.g. seeding options, dropping tables) on top of the built-in
 * cron cleanup.
 *
 * @api
 */
final class PluginLifecycle
{
    /** @var list<callable> */
    private array $onActivate = [];

    /** @var list<callable> */
    private array $onDeactivate = [];

    /**
     * @param string $pluginFile absolute path to the plugin's main file (the
     *                           value WordPress keys its lifecycle hooks on)
     */
    public function __construct(
        private readonly string $pluginFile,
        private readonly CronRegistrar $cronRegistrar,
    ) {}

    /**
     * Add an extra callback to run on plugin activation.
     */
    public function onActivate(callable $callback): void
    {
        $this->onActivate[] = $callback;
    }

    /**
     * Add an extra callback to run on plugin deactivation (before cron cleanup).
     */
    public function onDeactivate(callable $callback): void
    {
        $this->onDeactivate[] = $callback;
    }

    /**
     * Register the activation and deactivation hooks with WordPress.
     */
    public function register(): void
    {
        LifecycleSupport::registerActivation($this->pluginFile, [$this, 'activate']);
        LifecycleSupport::registerDeactivation($this->pluginFile, [$this, 'deactivate']);
    }

    /**
     * Activation entry point: runs registered activation callbacks.
     */
    public function activate(): void
    {
        foreach ($this->onActivate as $callback) {
            $callback();
        }
    }

    /**
     * Deactivation entry point: runs registered deactivation callbacks, then
     * clears every scheduled cron event so nothing survives deactivation.
     */
    public function deactivate(): void
    {
        foreach ($this->onDeactivate as $callback) {
            $callback();
        }

        $this->cronRegistrar->unregister();
    }
}
