<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Kernel;

use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Kernel\PluginLifecycle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PluginLifecycle::class)]
final class PluginLifecycleTest extends TestCase
{
    private const PLUGIN_FILE = '/plugins/middag/middag.php';

    protected function setUp(): void
    {
        $GLOBALS['__wp_test_activation_hooks'] = [];
        $GLOBALS['__wp_test_deactivation_hooks'] = [];
        $GLOBALS['__wp_test_actions'] = [];
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__wp_test_next_scheduled'] = [];
        $GLOBALS['__wp_test_recurring_events'] = [];
        $GLOBALS['__wp_test_unscheduled_events'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_activation_hooks'],
            $GLOBALS['__wp_test_deactivation_hooks'],
            $GLOBALS['__wp_test_actions'],
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__wp_test_next_scheduled'],
            $GLOBALS['__wp_test_recurring_events'],
            $GLOBALS['__wp_test_unscheduled_events'],
        );
    }

    #[Test]
    public function registerWiresActivationAndDeactivationCallbacks(): void
    {
        $lifecycle = new PluginLifecycle(self::PLUGIN_FILE, new CronRegistrar());

        $lifecycle->register();

        $activation = $GLOBALS['__wp_test_activation_hooks'][self::PLUGIN_FILE][0] ?? null;
        $deactivation = $GLOBALS['__wp_test_deactivation_hooks'][self::PLUGIN_FILE][0] ?? null;

        self::assertSame([$lifecycle, 'activate'], $activation, 'activation callback not registered');
        self::assertSame([$lifecycle, 'deactivate'], $deactivation, 'deactivation callback not registered');
    }

    #[Test]
    public function deactivateClearsScheduledCronHooks(): void
    {
        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);

        // Simulate the event already being scheduled in WP-Cron.
        $GLOBALS['__wp_test_next_scheduled']['middag_sync'] = 1_700_000_000;

        $lifecycle = new PluginLifecycle(self::PLUGIN_FILE, $registrar);
        $lifecycle->deactivate();

        $unscheduled = $GLOBALS['__wp_test_unscheduled_events'][0] ?? null;
        self::assertNotNull($unscheduled, 'the scheduled cron event was not cleared on deactivation');
        self::assertSame('middag_sync', $unscheduled['hook']);
        self::assertSame(1_700_000_000, $unscheduled['timestamp']);
    }

    #[Test]
    public function deactivateDoesNotUnscheduleHooksThatAreNotScheduled(): void
    {
        $registrar = new CronRegistrar();
        $registrar->addEvent('middag_sync', 'middag_hourly', static fn (): null => null);
        // No entry in __wp_test_next_scheduled -> wp_next_scheduled() returns false.

        $lifecycle = new PluginLifecycle(self::PLUGIN_FILE, $registrar);
        $lifecycle->deactivate();

        self::assertSame([], $GLOBALS['__wp_test_unscheduled_events'], 'nothing scheduled, so nothing should be cleared');
    }

    #[Test]
    public function activateRunsRegisteredActivationCallbacks(): void
    {
        $ran = false;
        $lifecycle = new PluginLifecycle(self::PLUGIN_FILE, new CronRegistrar());
        $lifecycle->onActivate(static function () use (&$ran): void {
            $ran = true;
        });

        $lifecycle->activate();

        self::assertTrue($ran, 'the activation callback did not run');
    }

    #[Test]
    public function deactivateRunsExtraCallbacksBeforeCronCleanup(): void
    {
        $ran = false;
        $lifecycle = new PluginLifecycle(self::PLUGIN_FILE, new CronRegistrar());
        $lifecycle->onDeactivate(static function () use (&$ran): void {
            $ran = true;
        });

        $lifecycle->deactivate();

        self::assertTrue($ran, 'the deactivation callback did not run');
    }
}
