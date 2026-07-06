<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Cron;

use Middag\WordPress\Cron\CronRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CronRegistrar::class)]
final class CronRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset every WP-cron recording seam this class exercises.
        $GLOBALS['__wp_test_recurring_events'] = [];
        $GLOBALS['__wp_test_next_scheduled'] = [];
        $GLOBALS['__wp_test_unscheduled_events'] = [];
        $GLOBALS['__wp_test_scheduled_events'] = [];
        $GLOBALS['__wp_test_actions'] = [];
        $GLOBALS['__wp_test_filters'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_recurring_events'],
            $GLOBALS['__wp_test_next_scheduled'],
            $GLOBALS['__wp_test_unscheduled_events'],
            $GLOBALS['__wp_test_scheduled_events'],
            $GLOBALS['__wp_test_actions'],
            $GLOBALS['__wp_test_filters'],
        );
    }

    // -------------------------------------------------------------------------
    // addEvent() / getRegisteredHooks()
    // -------------------------------------------------------------------------

    #[Test]
    public function getRegisteredHooksReturnsEmptyArrayWhenNoEventsAdded(): void
    {
        $registrar = new CronRegistrar();

        self::assertSame([], $registrar->getRegisteredHooks());
    }

    #[Test]
    public function addEventStoresEventsAndGetRegisteredHooksReturnsThemInOrder(): void
    {
        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        $registrar->addEvent('middag_cleanup', 'middag_daily_morning', static fn (): null => null);

        self::assertSame(['middag_sync', 'middag_cleanup'], $registrar->getRegisteredHooks());
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    #[Test]
    public function registerRegistersTheCronSchedulesFilter(): void
    {
        $registrar = new CronRegistrar();
        $registrar->register();

        self::assertArrayHasKey('cron_schedules', $GLOBALS['__wp_test_filters']);
        self::assertSame(
            [CronRegistrar::class, 'addIntervals'],
            $GLOBALS['__wp_test_filters']['cron_schedules'][0]['callback'],
        );
    }

    #[Test]
    public function registerAddsActionForEachEvent(): void
    {
        $callback = static fn (): null => null;

        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', $callback);
        $registrar->register();

        self::assertArrayHasKey('middag_sync', $GLOBALS['__wp_test_actions']);
        self::assertSame($callback, $GLOBALS['__wp_test_actions']['middag_sync'][0]['callback']);
    }

    #[Test]
    public function registerSchedulesEventWhenNotAlreadyScheduled(): void
    {
        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        $registrar->register();

        self::assertCount(1, $GLOBALS['__wp_test_recurring_events']);
        self::assertSame('middag_sync', $GLOBALS['__wp_test_recurring_events'][0]['hook']);
        self::assertSame('middag_hourly', $GLOBALS['__wp_test_recurring_events'][0]['recurrence']);
    }

    #[Test]
    public function registerSkipsSchedulingWhenAlreadyScheduled(): void
    {
        // wp_next_scheduled() returns a truthy timestamp → no new schedule.
        $GLOBALS['__wp_test_next_scheduled']['middag_sync'] = 1_900_000_000;

        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        $registrar->register();

        // Action is still wired, but nothing new is scheduled.
        self::assertArrayHasKey('middag_sync', $GLOBALS['__wp_test_actions']);
        self::assertSame([], $GLOBALS['__wp_test_recurring_events']);
    }

    #[Test]
    public function registerComputesNextRunForEachRecurrenceBranch(): void
    {
        $registrar = new CronRegistrar();
        $registrar->addEvent('hook_daily', 'middag_daily_morning', static fn (): null => null);
        $registrar->addEvent('hook_five', 'middag_five_minutes', static fn (): null => null);
        $registrar->addEvent('hook_hourly', 'middag_hourly', static fn (): null => null);
        $registrar->addEvent('hook_twice', 'middag_twicedaily', static fn (): null => null);
        $registrar->addEvent('hook_default', 'middag_every_minute', static fn (): null => null);

        $before = time();
        $registrar->register();
        $after = time();

        $byHook = [];
        foreach ($GLOBALS['__wp_test_recurring_events'] as $event) {
            $byHook[$event['hook']] = $event['timestamp'];
        }

        self::assertCount(5, $byHook);

        // five/hourly/twicedaily round up to the next interval boundary.
        self::assertSame(0, $byHook['hook_five'] % 300);
        self::assertSame(0, $byHook['hook_hourly'] % 3600);
        self::assertSame(0, $byHook['hook_twice'] % 43200);

        // default arm returns "now".
        self::assertGreaterThanOrEqual($before, $byHook['hook_default']);
        self::assertLessThanOrEqual($after, $byHook['hook_default']);

        // daily_morning is today 06:00 (if still ahead) or tomorrow 06:00.
        self::assertContains(
            $byHook['hook_daily'],
            [strtotime('06:00:00'), strtotime('tomorrow 06:00:00')],
        );
    }

    // -------------------------------------------------------------------------
    // unregister()
    // -------------------------------------------------------------------------

    #[Test]
    public function unregisterUnschedulesEventsThatAreScheduled(): void
    {
        $GLOBALS['__wp_test_next_scheduled']['middag_sync'] = 1_900_000_000;

        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        $registrar->unregister();

        self::assertCount(1, $GLOBALS['__wp_test_unscheduled_events']);
        self::assertSame(1_900_000_000, $GLOBALS['__wp_test_unscheduled_events'][0]['timestamp']);
        self::assertSame('middag_sync', $GLOBALS['__wp_test_unscheduled_events'][0]['hook']);
    }

    #[Test]
    public function unregisterSkipsEventsThatAreNotScheduled(): void
    {
        // No next-scheduled timestamp → wp_next_scheduled() returns false.
        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        $registrar->unregister();

        self::assertSame([], $GLOBALS['__wp_test_unscheduled_events']);
    }

    // -------------------------------------------------------------------------
    // addIntervals()
    // -------------------------------------------------------------------------

    #[Test]
    public function addIntervalsAddsEveryCustomInterval(): void
    {
        $schedules = CronRegistrar::addIntervals([]);

        self::assertArrayHasKey('middag_every_minute', $schedules);
        self::assertSame(60, $schedules['middag_every_minute']['interval']);
        self::assertSame('Every minute', $schedules['middag_every_minute']['display']);

        self::assertArrayHasKey('middag_five_minutes', $schedules);
        self::assertArrayHasKey('middag_fifteen_minutes', $schedules);
        self::assertArrayHasKey('middag_thirty_minutes', $schedules);
        self::assertArrayHasKey('middag_hourly', $schedules);
        self::assertArrayHasKey('middag_twicedaily', $schedules);

        self::assertArrayHasKey('middag_daily_morning', $schedules);
        self::assertSame(86400, $schedules['middag_daily_morning']['interval']);
        self::assertSame('Daily at 06:00', $schedules['middag_daily_morning']['display']);
    }

    #[Test]
    public function addIntervalsPreservesExistingSchedulesAndAddsTheRest(): void
    {
        $existing = [
            'middag_hourly' => ['interval' => 1, 'display' => 'Custom hourly'],
            'wp_daily' => ['interval' => 86400, 'display' => 'Once Daily'],
        ];

        $schedules = CronRegistrar::addIntervals($existing);

        // Pre-existing key is not overwritten.
        self::assertSame(1, $schedules['middag_hourly']['interval']);
        self::assertSame('Custom hourly', $schedules['middag_hourly']['display']);

        // Unrelated key survives untouched.
        self::assertSame($existing['wp_daily'], $schedules['wp_daily']);

        // Missing custom intervals are still added.
        self::assertSame(60, $schedules['middag_every_minute']['interval']);
        self::assertSame(300, $schedules['middag_five_minutes']['interval']);
    }
}
