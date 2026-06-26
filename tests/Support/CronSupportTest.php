<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\CronSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CronSupport::class)]
final class CronSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_next_scheduled'] = [];
        $GLOBALS['__wp_test_recurring_events'] = [];
        $GLOBALS['__wp_test_unscheduled_events'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_next_scheduled'],
            $GLOBALS['__wp_test_recurring_events'],
            $GLOBALS['__wp_test_unscheduled_events'],
        );
    }

    #[Test]
    public function nextScheduledReturnsFalseWhenHookIsNotScheduled(): void
    {
        self::assertFalse(CronSupport::nextScheduled('middag_unscheduled'));
    }

    #[Test]
    public function nextScheduledReturnsTheStoredTimestamp(): void
    {
        $GLOBALS['__wp_test_next_scheduled']['middag_cron'] = 1_700_000_000;

        self::assertSame(1_700_000_000, CronSupport::nextScheduled('middag_cron'));
    }

    #[Test]
    public function scheduleEventDelegatesToWordPress(): void
    {
        $result = CronSupport::scheduleEvent(1_700_000_000, 'middag_hourly', 'middag_cron');

        self::assertTrue($result);
        $recorded = $GLOBALS['__wp_test_recurring_events'][0] ?? null;
        self::assertNotNull($recorded, 'the event was not scheduled');
        self::assertSame(1_700_000_000, $recorded['timestamp']);
        self::assertSame('middag_hourly', $recorded['recurrence']);
        self::assertSame('middag_cron', $recorded['hook']);
    }

    #[Test]
    public function unscheduleEventDelegatesToWordPress(): void
    {
        $result = CronSupport::unscheduleEvent(1_700_000_000, 'middag_cron');

        self::assertTrue($result);
        $recorded = $GLOBALS['__wp_test_unscheduled_events'][0] ?? null;
        self::assertNotNull($recorded, 'the event was not unscheduled');
        self::assertSame(1_700_000_000, $recorded['timestamp']);
        self::assertSame('middag_cron', $recorded['hook']);
    }
}
