<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Definition;

use Middag\WordPress\Definition\CronScheduleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CronScheduleDefinition::class)]
final class CronScheduleDefinitionTest extends TestCase
{
    #[Test]
    public function constructorExposesSlugIntervalAndDisplay(): void
    {
        $definition = new CronScheduleDefinition('every_five_minutes', 300, 'Every five minutes');

        self::assertSame('every_five_minutes', $definition->slug);
        self::assertSame(300, $definition->intervalSeconds);
        self::assertSame('Every five minutes', $definition->display);
    }

    #[Test]
    public function isAPlainImmutableValueObjectPerInstance(): void
    {
        $hourly = new CronScheduleDefinition('hourly_custom', 3600, 'Hourly (custom)');
        $daily = new CronScheduleDefinition('daily_custom', 86400, 'Daily (custom)');

        self::assertNotSame($hourly->slug, $daily->slug);
        self::assertSame(3600, $hourly->intervalSeconds);
        self::assertSame(86400, $daily->intervalSeconds);
    }
}
