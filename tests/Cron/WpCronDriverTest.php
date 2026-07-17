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

use Middag\WordPress\Cron\JobScheduler;
use Middag\WordPress\Cron\WpCronDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of {@see WpCronDriver}. {@see JobSchedulerTest} already
 * exercises this class through {@see JobScheduler::make()},
 * but had no dedicated file of its own.
 *
 * @internal
 */
#[CoversClass(WpCronDriver::class)]
final class WpCronDriverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_scheduled_events'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_scheduled_events']);
    }

    #[Test]
    public function enqueueSchedulesAnImmediateSingleEventForTheGivenHook(): void
    {
        $driver = new WpCronDriver();

        $result = $driver->enqueue('acme/bus/deliver', ['payload' => 42]);

        self::assertTrue($result);
        self::assertCount(1, $GLOBALS['__wp_test_scheduled_events']);

        $event = $GLOBALS['__wp_test_scheduled_events'][0];
        self::assertSame('acme/bus/deliver', $event['hook']);
        self::assertSame(['payload' => 42], $event['args']);
        self::assertLessThanOrEqual(time(), $event['timestamp'], 'the event is scheduled for the current timestamp, i.e. ASAP');
    }

    #[Test]
    public function argsDefaultToAnEmptyArrayWhenNotProvided(): void
    {
        $driver = new WpCronDriver();

        $driver->enqueue('acme/bus/deliver');

        self::assertSame([], $GLOBALS['__wp_test_scheduled_events'][0]['args']);
    }

    #[Test]
    public function eachEnqueueCallSchedulesItsOwnEvent(): void
    {
        $driver = new WpCronDriver();

        $driver->enqueue('acme/bus/deliver', [1]);
        $driver->enqueue('acme/bus/deliver', [2]);

        self::assertCount(2, $GLOBALS['__wp_test_scheduled_events']);
        self::assertSame([1], $GLOBALS['__wp_test_scheduled_events'][0]['args']);
        self::assertSame([2], $GLOBALS['__wp_test_scheduled_events'][1]['args']);
    }
}
