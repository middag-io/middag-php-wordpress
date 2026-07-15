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
use Middag\WordPress\Support\CronSupport;

/**
 * Pure WP-Cron job driver — the zero-dependency fallback.
 *
 * Enqueues each job as a `wp_schedule_single_event` at the current timestamp,
 * so it runs on the next cron spawn. WP-Cron is a pseudo-cron (it only fires
 * on traffic unless a real system cron drives `wp-cron.php`), gives no retry,
 * no claiming and no admin UI — production hosts that need those guarantees
 * should run Action Scheduler ({@see ActionSchedulerDriver}); the outbox layer
 * above compensates for lost events by re-listing undelivered rows.
 *
 * @api
 */
final class WpCronDriver implements JobSchedulerInterface
{
    public function enqueue(string $hook, array $args = []): bool
    {
        return CronSupport::scheduleSingleEvent(time(), $hook, $args);
    }
}
