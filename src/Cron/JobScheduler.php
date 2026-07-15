<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Cron;

use Middag\WordPress\Cron\Contract\JobSchedulerInterface;

/**
 * Runtime-detecting factory for the async job port.
 *
 * Picks {@see ActionSchedulerDriver} when the Action Scheduler API is loaded
 * (WooCommerce ships it; it can also be installed standalone) and falls back
 * to {@see WpCronDriver} otherwise. Detection happens at call time, so build
 * the scheduler after `plugins_loaded` — probing earlier would miss an
 * Action Scheduler loaded by another plugin.
 *
 * @api
 */
final class JobScheduler
{
    /**
     * @param string    $group           Action Scheduler group tag (ignored by the
     *                                   WP-Cron fallback)
     * @param null|bool $actionScheduler force the driver choice (true = Action
     *                                   Scheduler, false = WP-Cron); null auto-detects
     */
    public static function make(string $group = '', ?bool $actionScheduler = null): JobSchedulerInterface
    {
        $actionScheduler ??= ActionSchedulerDriver::isAvailable();

        return $actionScheduler ? new ActionSchedulerDriver($group) : new WpCronDriver();
    }
}
