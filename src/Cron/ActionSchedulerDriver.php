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
 * Action Scheduler job driver — the preferred backend when present.
 *
 * Action Scheduler (bundled with WooCommerce, also shippable standalone)
 * gives a persistent claimed queue with retries, an admin UI under
 * Tools → Scheduled Actions and a WP-CLI runner. Jobs are enqueued as async
 * actions (run on the next queue pass, no timestamp) under an optional group
 * so a host with several MIDDAG plugins can tell their queues apart.
 *
 * The driver degrades to a refused enqueue (false) when the Action Scheduler
 * API is absent at call time; use {@see JobScheduler::make()} to fall back to
 * {@see WpCronDriver} instead of ever hitting that path.
 *
 * @api
 */
final readonly class ActionSchedulerDriver implements JobSchedulerInterface
{
    /**
     * @param string $group Action Scheduler group tag (e.g. the plugin slug)
     */
    public function __construct(
        private string $group = '',
    ) {}

    public static function isAvailable(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    public function enqueue(string $hook, array $args = []): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        return as_enqueue_async_action($hook, $args, $this->group) > 0;
    }
}
