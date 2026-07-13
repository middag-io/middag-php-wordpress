<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Cron;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\WordPress\Cron\Enum\CronInterval;
use Middag\WordPress\Support\CronSupport;
use Middag\WordPress\Support\HookSupport;

/**
 * Per-component WP-Cron registrar.
 *
 * Wires each event to a {@see CronInterval} instead of a magic recurrence
 * string, and registers the custom intervals on the `cron_schedules` filter
 * keyed by the host component (resolved through the plugin's own DI), so two
 * plugins in the same request keep independent schedules. Interval labels are
 * translated through the host {@see TranslatorInterface}.
 *
 * @api
 */
final class CronRegistrar
{
    /**
     * @var array<array{hook: string, interval: CronInterval, callback: callable}>
     */
    private array $events = [];

    public function __construct(
        private readonly HostComponentContextInterface $context,
        private readonly TranslatorInterface $translator,
    ) {}

    public function addEvent(string $hook, CronInterval $interval, callable $callback): void
    {
        $this->events[] = [
            'hook' => $hook,
            'interval' => $interval,
            'callback' => $callback,
        ];
    }

    public function register(): void
    {
        // Register custom intervals (keyed per-component).
        HookSupport::addFilter('cron_schedules', [$this, 'registerIntervals']);

        // Register events and their callbacks.
        foreach ($this->events as $event) {
            HookSupport::addAction($event['hook'], $event['callback']);

            if (!CronSupport::nextScheduled($event['hook'])) {
                CronSupport::scheduleEvent(
                    $event['interval']->nextRun(),
                    $event['interval']->scheduleKey($this->context->componentName()),
                    $event['hook'],
                );
            }
        }
    }

    public function unregister(): void
    {
        foreach ($this->events as $event) {
            $timestamp = CronSupport::nextScheduled($event['hook']);
            if ($timestamp) {
                CronSupport::unscheduleEvent($timestamp, $event['hook']);
            }
        }
    }

    /**
     * Filter callback: add every {@see CronInterval} to WP cron schedules,
     * keyed `{component}_{case}` so plugins never collide on a shared slot.
     * Pre-existing keys are left untouched.
     */
    public function registerIntervals(array $schedules): array
    {
        $component = $this->context->componentName();

        foreach (CronInterval::cases() as $interval) {
            $key = $interval->scheduleKey($component);
            if (!isset($schedules[$key])) {
                $schedules[$key] = [
                    'interval' => $interval->seconds(),
                    'display' => $interval->label($this->translator),
                ];
            }
        }

        return $schedules;
    }

    /**
     * Get all registered event hooks.
     */
    public function getRegisteredHooks(): array
    {
        return array_column($this->events, 'hook');
    }
}
