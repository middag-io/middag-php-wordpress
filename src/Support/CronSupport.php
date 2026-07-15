<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Support;

/**
 * Thin wrapper over WordPress's WP-Cron scheduling functions.
 *
 * Isolates `wp_next_scheduled`/`wp_schedule_event`/`wp_unschedule_event` behind
 * a small seam so the cron registrar never touches the globals directly. Each
 * method degrades to a safe "not scheduled / nothing to do" result when the
 * cron API is absent.
 *
 * @internal
 */
final class CronSupport
{
    /**
     * Next scheduled UNIX timestamp for a hook, or false when not scheduled
     * (or the cron API is unavailable).
     *
     * @param array<int, mixed> $args
     */
    public static function nextScheduled(string $hook, array $args = []): false|int
    {
        if (!function_exists('wp_next_scheduled')) {
            return false;
        }

        return wp_next_scheduled($hook, $args);
    }

    /**
     * Schedule a recurring event. Returns false when scheduling fails or the
     * cron API is unavailable.
     *
     * @param array<int, mixed> $args
     */
    public static function scheduleEvent(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        if (!function_exists('wp_schedule_event')) {
            return false;
        }

        return wp_schedule_event($timestamp, $recurrence, $hook, $args);
    }

    /**
     * Schedule a one-off event. Returns false when scheduling fails or the
     * cron API is unavailable.
     *
     * WP-Cron suppresses a duplicate single event when an identical hook+args
     * pair is already scheduled within the next ten minutes; callers that need
     * per-job identity must make $args distinct per job.
     *
     * @param array<int, mixed> $args
     */
    public static function scheduleSingleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        if (!function_exists('wp_schedule_single_event')) {
            return false;
        }

        return wp_schedule_single_event($timestamp, $hook, $args);
    }

    /**
     * Unschedule a single occurrence of an event. Returns false when the cron
     * API is unavailable.
     *
     * @param array<int, mixed> $args
     */
    public static function unscheduleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        if (!function_exists('wp_unschedule_event')) {
            return false;
        }

        return wp_unschedule_event($timestamp, $hook, $args);
    }
}
