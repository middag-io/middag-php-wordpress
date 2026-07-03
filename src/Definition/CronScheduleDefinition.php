<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Definition;

use Middag\WordPress\Cron\CronRegistrar;

/**
 * Declarative custom cron interval, surfaced through the `cron_schedules`
 * filter so {@see CronRegistrar} handlers can schedule
 * against it.
 *
 * @api
 */
final readonly class CronScheduleDefinition
{
    /**
     * @param non-empty-string $slug
     * @param positive-int     $intervalSeconds
     */
    public function __construct(
        public string $slug,
        public int $intervalSeconds,
        public string $display,
    ) {}
}
