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
use Middag\WordPress\Cron\Enum\CronInterval;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Translation\WpTranslator;
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
        $GLOBALS['__wp_test_translations'] = [];
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
            $GLOBALS['__wp_test_translations'],
        );
    }

    // -------------------------------------------------------------------------
    // addEvent() / getRegisteredHooks()
    // -------------------------------------------------------------------------

    #[Test]
    public function getRegisteredHooksReturnsEmptyArrayWhenNoEventsAdded(): void
    {
        self::assertSame([], $this->makeRegistrar()->getRegisteredHooks());
    }

    #[Test]
    public function addEventStoresEventsAndGetRegisteredHooksReturnsThemInOrder(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->addEvent('middag_sync', CronInterval::Hourly, static fn (): null => null);
        $registrar->addEvent('middag_cleanup', CronInterval::DailyMorning, static fn (): null => null);

        self::assertSame(['middag_sync', 'middag_cleanup'], $registrar->getRegisteredHooks());
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    #[Test]
    public function registerRegistersTheCronSchedulesFilter(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register();

        self::assertArrayHasKey('cron_schedules', $GLOBALS['__wp_test_filters']);
        self::assertSame(
            [$registrar, 'registerIntervals'],
            $GLOBALS['__wp_test_filters']['cron_schedules'][0]['callback'],
        );
    }

    #[Test]
    public function registerAddsActionForEachEvent(): void
    {
        $callback = static fn (): null => null;

        $registrar = $this->makeRegistrar();
        $registrar->addEvent('middag_sync', CronInterval::Hourly, $callback);
        $registrar->register();

        self::assertArrayHasKey('middag_sync', $GLOBALS['__wp_test_actions']);
        self::assertSame($callback, $GLOBALS['__wp_test_actions']['middag_sync'][0]['callback']);
    }

    #[Test]
    public function registerSchedulesEventWithComponentPrefixedRecurrence(): void
    {
        $registrar = $this->makeRegistrar('acme');
        $registrar->addEvent('acme_sync', CronInterval::Hourly, static fn (): null => null);
        $registrar->register();

        self::assertCount(1, $GLOBALS['__wp_test_recurring_events']);
        self::assertSame('acme_sync', $GLOBALS['__wp_test_recurring_events'][0]['hook']);
        // Recurrence key is {component}_{case value}, not a magic string.
        self::assertSame('acme_hourly', $GLOBALS['__wp_test_recurring_events'][0]['recurrence']);
    }

    #[Test]
    public function registerSkipsSchedulingWhenAlreadyScheduled(): void
    {
        // wp_next_scheduled() returns a truthy timestamp → no new schedule.
        $GLOBALS['__wp_test_next_scheduled']['middag_sync'] = 1_900_000_000;

        $registrar = $this->makeRegistrar();
        $registrar->addEvent('middag_sync', CronInterval::Hourly, static fn (): null => null);
        $registrar->register();

        // Action is still wired, but nothing new is scheduled.
        self::assertArrayHasKey('middag_sync', $GLOBALS['__wp_test_actions']);
        self::assertSame([], $GLOBALS['__wp_test_recurring_events']);
    }

    #[Test]
    public function registerComputesNextRunForEachInterval(): void
    {
        $registrar = $this->makeRegistrar();
        foreach (CronInterval::cases() as $interval) {
            $registrar->addEvent('hook_' . $interval->value, $interval, static fn (): null => null);
        }

        $registrar->register();

        $byHook = [];
        foreach ($GLOBALS['__wp_test_recurring_events'] as $event) {
            $byHook[$event['hook']] = $event['timestamp'];
        }

        self::assertCount(count(CronInterval::cases()), $byHook);

        // Interval cases round up to their next clock boundary.
        foreach ([CronInterval::EveryMinute, CronInterval::FiveMinutes, CronInterval::FifteenMinutes, CronInterval::ThirtyMinutes, CronInterval::Hourly, CronInterval::TwiceDaily] as $interval) {
            self::assertSame(
                0,
                $byHook['hook_' . $interval->value] % $interval->seconds(),
                sprintf('%s should land on a %d-second boundary', $interval->name, $interval->seconds()),
            );
        }

        // daily_morning is today 06:00 (if still ahead) or tomorrow 06:00.
        self::assertContains(
            $byHook['hook_daily_morning'],
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

        $registrar = $this->makeRegistrar();
        $registrar->addEvent('middag_sync', CronInterval::Hourly, static fn (): null => null);
        $registrar->unregister();

        self::assertCount(1, $GLOBALS['__wp_test_unscheduled_events']);
        self::assertSame(1_900_000_000, $GLOBALS['__wp_test_unscheduled_events'][0]['timestamp']);
        self::assertSame('middag_sync', $GLOBALS['__wp_test_unscheduled_events'][0]['hook']);
    }

    #[Test]
    public function unregisterSkipsEventsThatAreNotScheduled(): void
    {
        // No next-scheduled timestamp → wp_next_scheduled() returns false.
        $registrar = $this->makeRegistrar();
        $registrar->addEvent('middag_sync', CronInterval::Hourly, static fn (): null => null);
        $registrar->unregister();

        self::assertSame([], $GLOBALS['__wp_test_unscheduled_events']);
    }

    // -------------------------------------------------------------------------
    // registerIntervals()
    // -------------------------------------------------------------------------

    #[Test]
    public function registerIntervalsAddsEveryCustomIntervalKeyedByComponent(): void
    {
        $schedules = $this->makeRegistrar()->registerIntervals([]);

        self::assertArrayHasKey('middag_every_minute', $schedules);
        self::assertSame(60, $schedules['middag_every_minute']['interval']);
        self::assertSame('Every minute', $schedules['middag_every_minute']['display']);

        self::assertArrayHasKey('middag_five_minutes', $schedules);
        self::assertArrayHasKey('middag_fifteen_minutes', $schedules);
        self::assertArrayHasKey('middag_thirty_minutes', $schedules);
        self::assertArrayHasKey('middag_hourly', $schedules);
        self::assertArrayHasKey('middag_twice_daily', $schedules);

        self::assertArrayHasKey('middag_daily_morning', $schedules);
        self::assertSame(86400, $schedules['middag_daily_morning']['interval']);
        self::assertSame('Daily at 06:00', $schedules['middag_daily_morning']['display']);
    }

    #[Test]
    public function registerIntervalsPreservesExistingSchedulesAndAddsTheRest(): void
    {
        $existing = [
            'middag_hourly' => ['interval' => 1, 'display' => 'Custom hourly'],
            'wp_daily' => ['interval' => 86400, 'display' => 'Once Daily'],
        ];

        $schedules = $this->makeRegistrar()->registerIntervals($existing);

        // Pre-existing key is not overwritten.
        self::assertSame(1, $schedules['middag_hourly']['interval']);
        self::assertSame('Custom hourly', $schedules['middag_hourly']['display']);

        // Unrelated key survives untouched.
        self::assertSame($existing['wp_daily'], $schedules['wp_daily']);

        // Missing custom intervals are still added.
        self::assertSame(60, $schedules['middag_every_minute']['interval']);
        self::assertSame(300, $schedules['middag_five_minutes']['interval']);
    }

    #[Test]
    public function registerIntervalsRoutesLabelsThroughTheTranslator(): void
    {
        // A loaded translation for the lib's default domain is honoured — proof
        // the label is not a hardcoded English literal.
        $GLOBALS['__wp_test_translations']['middag']['Every hour'] = 'A cada hora';

        $schedules = $this->makeRegistrar()->registerIntervals([]);

        self::assertSame('A cada hora', $schedules['middag_hourly']['display']);
    }

    #[Test]
    public function differentComponentsProduceDistinctIntervalKeys(): void
    {
        $acme = $this->makeRegistrar('acme')->registerIntervals([]);
        $globex = $this->makeRegistrar('globex')->registerIntervals([]);

        self::assertArrayHasKey('acme_hourly', $acme);
        self::assertArrayNotHasKey('globex_hourly', $acme);

        self::assertArrayHasKey('globex_hourly', $globex);
        self::assertArrayNotHasKey('acme_hourly', $globex);
    }

    private function makeRegistrar(string $component = 'middag'): CronRegistrar
    {
        return new CronRegistrar(
            new WpComponentContext($component, '5.0.0'),
            new WpTranslator(),
        );
    }
}
