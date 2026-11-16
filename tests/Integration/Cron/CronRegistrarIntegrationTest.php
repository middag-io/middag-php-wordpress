<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Cron;

use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Cron\Enum\CronInterval;
use Middag\WordPress\Cron\JobScheduler;
use Middag\WordPress\Cron\WpCronDriver;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Tests\Integration\Translation\WpTranslatorIntegrationTest;
use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cron schedules and events registered in the real WP-Cron option.
 *
 * @internal
 */
#[CoversClass(CronRegistrar::class)]
#[CoversClass(WpCronDriver::class)]
#[CoversClass(JobScheduler::class)]
final class CronRegistrarIntegrationTest extends TestCase
{
    private const string COMPONENT = 'middaglib_it';

    private const string HOOK = 'middaglib_it_cron_sync';

    private const string JOB_HOOK = 'middaglib_it_job';

    private CronRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        WpTranslatorIntegrationTest::loadTextDomain();

        $this->registrar = new CronRegistrar(
            new WpComponentContext(self::COMPONENT, '1.0.0'),
            new WpTranslator(WpTranslatorIntegrationTest::DOMAIN),
        );
        $this->registrar->addEvent(self::HOOK, CronInterval::FiveMinutes, static function (): void {});
    }

    protected function tearDown(): void
    {
        $this->registrar->unregister();
        wp_clear_scheduled_hook(self::JOB_HOOK, [7]);
        remove_filter('cron_schedules', [$this->registrar, 'registerIntervals']);
        remove_all_actions(self::HOOK);
        unload_textdomain(WpTranslatorIntegrationTest::DOMAIN);
        parent::tearDown();
    }

    #[Test]
    public function registerAddsTheComponentSchedulesWithTranslatedLabels(): void
    {
        $this->registrar->register();

        $schedules = wp_get_schedules();
        $key = CronInterval::FiveMinutes->scheduleKey(self::COMPONENT);

        self::assertSame(300, $schedules[$key]['interval'] ?? null);
        self::assertSame('A cada 5 minutos', $schedules[$key]['display'] ?? null);
        self::assertArrayHasKey('hourly', $schedules, 'Core schedules stay registered.');
    }

    #[Test]
    public function registerSchedulesTheRecurringEventOnce(): void
    {
        $this->registrar->register();
        $first = wp_next_scheduled(self::HOOK);

        self::assertIsInt($first);
        self::assertSame(CronInterval::FiveMinutes->scheduleKey(self::COMPONENT), wp_get_schedule(self::HOOK));
        self::assertNotFalse(has_action(self::HOOK));

        $this->registrar->register();
        self::assertSame($first, wp_next_scheduled(self::HOOK));
        self::assertCount(1, array_filter(
            _get_cron_array(),
            static fn (array $hooks): bool => isset($hooks[self::HOOK]),
        ));
    }

    #[Test]
    public function unregisterRemovesTheScheduledEvent(): void
    {
        $this->registrar->register();
        $this->registrar->unregister();

        self::assertFalse(wp_next_scheduled(self::HOOK));
    }

    #[Test]
    public function theWpCronDriverEnqueuesASingleEvent(): void
    {
        $scheduler = JobScheduler::make();

        self::assertInstanceOf(WpCronDriver::class, $scheduler, 'Without Action Scheduler the factory falls back to WP-Cron.');
        self::assertTrue($scheduler->enqueue(self::JOB_HOOK, [7]));
        self::assertIsInt(wp_next_scheduled(self::JOB_HOOK, [7]));
        self::assertFalse(wp_get_schedule(self::JOB_HOOK, [7]));
    }
}
