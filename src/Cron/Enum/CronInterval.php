<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Cron\Enum;

use Middag\Framework\Translation\Contract\TranslatorInterface;

/**
 * Closed catalog of custom WP-Cron recurrences the adapter offers.
 *
 * Replaces the old magic-string schedule keys: a typo now fails at compile time
 * instead of silently scheduling nothing. Each case owns its interval length,
 * its translatable label, and the timestamp of its next run, so the registrar
 * never branches on an untyped string. The bare case value is the recurrence
 * suffix; {@see self::scheduleKey()} prefixes it with the host component so two
 * plugins in the same request never collide on a shared `cron_schedules` slot.
 *
 * @api
 */
enum CronInterval: string
{
    case EveryMinute = 'every_minute';

    case FiveMinutes = 'five_minutes';

    case FifteenMinutes = 'fifteen_minutes';

    case ThirtyMinutes = 'thirty_minutes';

    case Hourly = 'hourly';

    case TwiceDaily = 'twice_daily';

    case DailyMorning = 'daily_morning';

    /**
     * Interval length in seconds, as WordPress expects on the `cron_schedules`
     * filter.
     */
    public function seconds(): int
    {
        return match ($this) {
            self::EveryMinute => 60,
            self::FiveMinutes => 300,
            self::FifteenMinutes => 900,
            self::ThirtyMinutes => 1800,
            self::Hourly => 3600,
            self::TwiceDaily => 43200,
            self::DailyMorning => 86400,
        };
    }

    /**
     * Human-readable display label, routed through the host {@see TranslatorInterface}
     * so the schedule name is localisable instead of hardcoded English.
     */
    public function label(TranslatorInterface $translator): string
    {
        return $translator->get(match ($this) {
            self::EveryMinute => 'Every minute',
            self::FiveMinutes => 'Every 5 minutes',
            self::FifteenMinutes => 'Every 15 minutes',
            self::ThirtyMinutes => 'Every 30 minutes',
            self::Hourly => 'Every hour',
            self::TwiceDaily => 'Twice daily',
            self::DailyMorning => 'Daily at 06:00',
        });
    }

    /**
     * UNIX timestamp of the first run WordPress should schedule.
     *
     * Interval cases round up to the next clock boundary (a five-minute event
     * fires at the next :00/:05/:10…); {@see self::DailyMorning} fires at the
     * next 06:00. No untyped `default` arm — the type guarantees the branch.
     */
    public function nextRun(): int
    {
        $now = time();

        if ($this === self::DailyMorning) {
            return $this->nextMorning($now);
        }

        return $now + ($this->seconds() - ($now % $this->seconds()));
    }

    /**
     * WordPress schedule key: the host component name joined to the case value,
     * e.g. `my-plugin_hourly`. Keeps each component's intervals in their own
     * namespace on the shared `cron_schedules` filter.
     */
    public function scheduleKey(string $component): string
    {
        return $component . '_' . $this->value;
    }

    /**
     * Next 06:00 boundary: today's if still ahead, otherwise tomorrow's.
     */
    private function nextMorning(int $now): int
    {
        $todaySixAm = strtotime('06:00:00');

        return $todaySixAm > $now ? $todaySixAm : strtotime('tomorrow 06:00:00');
    }
}
