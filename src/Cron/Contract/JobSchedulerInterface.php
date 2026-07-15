<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Cron\Contract;

use Middag\WordPress\Cron\ActionSchedulerDriver;
use Middag\WordPress\Cron\WpCronDriver;

/**
 * Contract for enqueuing one-off async jobs on a WordPress host.
 *
 * The WordPress counterpart of Moodle's adhoc-task seam: a job is a hook name
 * plus positional arguments, executed out-of-band by whichever queue backend
 * the driver wraps. Consumers register a listener on the hook
 * (`add_action($hook, …)`) and enqueue through this port, staying agnostic of
 * the backend — Action Scheduler when present ({@see ActionSchedulerDriver}),
 * plain WP-Cron otherwise ({@see WpCronDriver}).
 *
 * @api
 */
interface JobSchedulerInterface
{
    /**
     * Enqueue a one-off job for as-soon-as-possible async execution.
     *
     * @param string            $hook the action hook the job fires
     * @param array<int, mixed> $args positional arguments passed to the hook
     *                                listener; must be distinct per job under
     *                                WP-Cron (identical hook+args pairs within
     *                                ten minutes are suppressed)
     *
     * @return bool true when the job was accepted by the backend
     */
    public function enqueue(string $hook, array $args = []): bool;
}
