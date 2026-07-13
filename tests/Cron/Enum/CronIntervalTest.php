<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Cron\Enum;

use Middag\WordPress\Cron\Enum\CronInterval;
use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CronInterval::class)]
final class CronIntervalTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_translations'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_translations']);
    }

    #[Test]
    #[DataProvider('intervalProvider')]
    public function caseValueIsStableSnakeCase(CronInterval $interval, string $value): void
    {
        self::assertSame($value, $interval->value);
        self::assertSame($interval, CronInterval::from($value));
    }

    #[Test]
    #[DataProvider('intervalProvider')]
    public function secondsReturnsIntervalLength(CronInterval $interval, string $value, int $seconds): void
    {
        self::assertSame($seconds, $interval->seconds());
    }

    #[Test]
    #[DataProvider('intervalProvider')]
    public function labelFallsBackToTheEnglishSource(CronInterval $interval, string $value, int $seconds, string $label): void
    {
        // No translation loaded → WpTranslator passes the source string through.
        self::assertSame($label, $interval->label(new WpTranslator()));
    }

    #[Test]
    public function labelIsTranslatedWhenTheDomainProvidesIt(): void
    {
        $GLOBALS['__wp_test_translations']['middag']['Every hour'] = 'A cada hora';

        self::assertSame('A cada hora', CronInterval::Hourly->label(new WpTranslator()));
    }

    #[Test]
    #[DataProvider('intervalProvider')]
    public function scheduleKeyPrefixesTheComponent(CronInterval $interval, string $value): void
    {
        self::assertSame('acme_' . $value, $interval->scheduleKey('acme'));
    }

    #[Test]
    #[DataProvider('intervalProvider')]
    public function nextRunLandsInTheFuture(CronInterval $interval): void
    {
        self::assertGreaterThan(time(), $interval->nextRun());
    }

    /**
     * @return iterable<string, array{CronInterval, string, int, string}>
     */
    public static function intervalProvider(): iterable
    {
        yield 'every minute' => [CronInterval::EveryMinute, 'every_minute', 60, 'Every minute'];

        yield 'five minutes' => [CronInterval::FiveMinutes, 'five_minutes', 300, 'Every 5 minutes'];

        yield 'fifteen minutes' => [CronInterval::FifteenMinutes, 'fifteen_minutes', 900, 'Every 15 minutes'];

        yield 'thirty minutes' => [CronInterval::ThirtyMinutes, 'thirty_minutes', 1800, 'Every 30 minutes'];

        yield 'hourly' => [CronInterval::Hourly, 'hourly', 3600, 'Every hour'];

        yield 'twice daily' => [CronInterval::TwiceDaily, 'twice_daily', 43200, 'Twice daily'];

        yield 'daily morning' => [CronInterval::DailyMorning, 'daily_morning', 86400, 'Daily at 06:00'];
    }

    #[Test]
    public function nextRunRoundsIntervalCasesToTheirClockBoundary(): void
    {
        foreach ([CronInterval::EveryMinute, CronInterval::FiveMinutes, CronInterval::FifteenMinutes, CronInterval::ThirtyMinutes, CronInterval::Hourly, CronInterval::TwiceDaily] as $interval) {
            $nextRun = $interval->nextRun();

            self::assertSame(
                0,
                $nextRun % $interval->seconds(),
                sprintf('%s should land on a %d-second boundary', $interval->name, $interval->seconds()),
            );
            // Boundary is at most one interval ahead of now.
            self::assertLessThanOrEqual(time() + $interval->seconds(), $nextRun);
        }
    }

    #[Test]
    public function nextRunForDailyMorningIsTheNextSixAm(): void
    {
        self::assertContains(
            CronInterval::DailyMorning->nextRun(),
            [strtotime('06:00:00'), strtotime('tomorrow 06:00:00')],
        );
    }
}
