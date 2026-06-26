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

use Middag\WordPress\Support\CronSupport;
use Middag\WordPress\Support\HookSupport;

final class CronRegistrar
{
    /**
     * Custom cron intervals.
     * [key => [interval_seconds, display_label]].
     */
    private const INTERVALS = [
        'middag_every_minute' => [60, 'A cada minuto'],
        'middag_five_minutes' => [300, 'A cada 5 minutos'],
        'middag_fifteen_minutes' => [900, 'A cada 15 minutos'],
        'middag_thirty_minutes' => [1800, 'A cada 30 minutos'],
        'middag_hourly' => [3600, 'A cada hora'],
        'middag_twicedaily' => [43200, 'Duas vezes ao dia'],
        'middag_daily_morning' => [86400, 'Diário às 06:00'],
    ];

    /**
     * @var array<array{hook: string, recurrence: string, callback: callable}>
     */
    private array $events = [];

    public function addEvent(string $hook, string $recurrence, callable $callback): void
    {
        $this->events[] = [
            'hook' => $hook,
            'recurrence' => $recurrence,
            'callback' => $callback,
        ];
    }

    public function register(): void
    {
        // Register custom intervals
        HookSupport::addFilter('cron_schedules', [self::class, 'addIntervals']);

        // Register events and their callbacks
        foreach ($this->events as $event) {
            HookSupport::addAction($event['hook'], $event['callback']);

            if (!CronSupport::nextScheduled($event['hook'])) {
                $nextRun = $this->calculateNextRun($event['recurrence']);
                CronSupport::scheduleEvent($nextRun, $event['recurrence'], $event['hook']);
            }
        }
    }

    public function unregister(): void
    {
        foreach ($this->events as $event) {
            $timestamp = CronSupport::nextScheduled($event['hook']);
            if ($timestamp) {
                CronSupport::unscheduleEvent($timestamp, $event['hook']);
            }
        }
    }

    /**
     * Filter callback: add custom intervals to WP cron schedules.
     */
    public static function addIntervals(array $schedules): array
    {
        foreach (self::INTERVALS as $key => [$interval, $display]) {
            if (!isset($schedules[$key])) {
                $schedules[$key] = [
                    'interval' => $interval,
                    'display' => $display,
                ];
            }
        }

        return $schedules;
    }

    /**
     * Get all registered event hooks.
     */
    public function getRegisteredHooks(): array
    {
        return array_column($this->events, 'hook');
    }

    private function calculateNextRun(string $recurrence): int
    {
        $now = time();

        return match ($recurrence) {
            'middag_daily_morning' => $this->nextMorning(),
            'middag_five_minutes' => $now + (300 - ($now % 300)),
            'middag_hourly' => $now + (3600 - ($now % 3600)),
            'middag_twicedaily' => $now + (43200 - ($now % 43200)),
            default => $now,
        };
    }

    private function nextMorning(): int
    {
        $today6am = strtotime('06:00:00');

        return $today6am > time() ? $today6am : strtotime('tomorrow 06:00:00');
    }
}
