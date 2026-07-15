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

use Middag\WordPress\Cron\ActionSchedulerDriver;
use Middag\WordPress\Cron\JobScheduler;
use Middag\WordPress\Cron\WpCronDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(JobScheduler::class)]
#[CoversClass(ActionSchedulerDriver::class)]
#[CoversClass(WpCronDriver::class)]
final class JobSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_as_actions'] = [];
        $GLOBALS['__wp_test_scheduled_events'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_as_actions'], $GLOBALS['__wp_test_scheduled_events']);
    }

    #[Test]
    public function makePicksActionSchedulerWhenForcedOn(): void
    {
        self::assertInstanceOf(ActionSchedulerDriver::class, JobScheduler::make('middag', true));
    }

    #[Test]
    public function makePicksWpCronWhenForcedOff(): void
    {
        self::assertInstanceOf(WpCronDriver::class, JobScheduler::make('middag', false));
    }

    #[Test]
    public function makeAutoDetectsFromTheActionSchedulerApi(): void
    {
        // The test suite stubs as_enqueue_async_action, so detection sees it.
        self::assertInstanceOf(ActionSchedulerDriver::class, JobScheduler::make());
    }

    #[Test]
    public function actionSchedulerDriverEnqueuesUnderItsGroup(): void
    {
        $driver = new ActionSchedulerDriver('middag-account');

        self::assertTrue($driver->enqueue('middag/bus/async_delivery', [42]));
        self::assertSame(
            [['hook' => 'middag/bus/async_delivery', 'args' => [42], 'group' => 'middag-account']],
            $GLOBALS['__wp_test_as_actions'],
        );
    }

    #[Test]
    public function wpCronDriverSchedulesAnImmediateSingleEvent(): void
    {
        $driver = new WpCronDriver();

        self::assertTrue($driver->enqueue('middag/bus/async_delivery', [42]));
        self::assertCount(1, $GLOBALS['__wp_test_scheduled_events']);

        $event = $GLOBALS['__wp_test_scheduled_events'][0];

        self::assertSame('middag/bus/async_delivery', $event['hook']);
        self::assertSame([42], $event['args']);
        self::assertLessThanOrEqual(time(), $event['timestamp']);
    }

    #[Test]
    public function driversPassDistinctArgsPerJob(): void
    {
        $driver = new ActionSchedulerDriver();
        $driver->enqueue('middag/bus/async_delivery', [1]);
        $driver->enqueue('middag/bus/async_delivery', [2]);

        self::assertSame([1], $GLOBALS['__wp_test_as_actions'][0]['args']);
        self::assertSame([2], $GLOBALS['__wp_test_as_actions'][1]['args']);
    }
}
