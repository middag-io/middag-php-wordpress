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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of {@see ActionSchedulerDriver}. {@see JobSchedulerTest}
 * already exercises this class through {@see JobScheduler::make()},
 * but had no dedicated file of its own.
 *
 * @internal
 */
#[CoversClass(ActionSchedulerDriver::class)]
final class ActionSchedulerDriverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_as_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_as_actions'], $GLOBALS['__wp_test_as_action_id_override']);
    }

    #[Test]
    public function isAvailableIsTrueWhenTheActionSchedulerApiFunctionExists(): void
    {
        // The test suite stubs as_enqueue_async_action() (see tests/bootstrap.php
        // via stubs/wp-stubs.php), so the API always appears available here.
        self::assertTrue(ActionSchedulerDriver::isAvailable());
    }

    #[Test]
    public function enqueueRecordsTheHookArgsAndGroupOnTheActionSchedulerApi(): void
    {
        $driver = new ActionSchedulerDriver('acme-group');

        $result = $driver->enqueue('acme/bus/deliver', ['payload' => 42]);

        self::assertTrue($result);
        self::assertSame(
            [['hook' => 'acme/bus/deliver', 'args' => ['payload' => 42], 'group' => 'acme-group']],
            $GLOBALS['__wp_test_as_actions'],
        );
    }

    #[Test]
    public function groupDefaultsToAnEmptyStringWhenNotProvided(): void
    {
        $driver = new ActionSchedulerDriver();

        $driver->enqueue('acme/bus/deliver');

        self::assertSame('', $GLOBALS['__wp_test_as_actions'][0]['group']);
        self::assertSame([], $GLOBALS['__wp_test_as_actions'][0]['args'], 'args default to an empty array');
    }

    #[Test]
    public function enqueueReturnsFalseWhenTheActionSchedulerApiRefusesTheJob(): void
    {
        // Action Scheduler's own as_enqueue_async_action() returns an action id
        // <= 0 (typically 0) when it declines to enqueue; the driver must
        // translate that into a plain false rather than a truthy id.
        $GLOBALS['__wp_test_as_action_id_override'] = 0;

        $driver = new ActionSchedulerDriver();

        self::assertFalse($driver->enqueue('acme/bus/deliver', [1]));
    }

    #[Test]
    public function eachEnqueueCallIsRecordedIndependently(): void
    {
        $driver = new ActionSchedulerDriver('acme-group');

        $driver->enqueue('acme/bus/deliver', [1]);
        $driver->enqueue('acme/bus/deliver', [2]);

        self::assertCount(2, $GLOBALS['__wp_test_as_actions']);
        self::assertSame([1], $GLOBALS['__wp_test_as_actions'][0]['args']);
        self::assertSame([2], $GLOBALS['__wp_test_as_actions'][1]['args']);
    }
}
