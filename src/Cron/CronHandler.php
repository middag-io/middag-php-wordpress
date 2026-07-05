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

use Closure;
use Middag\WordPress\Support\LogSupport;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * @api
 */
final class CronHandler
{
    /**
     * Create a callback that resolves a service from the container and calls a method.
     *
     * Usage:
     *   $cronRegistrar->addEvent(
     *       'middag_sync_invoices',
     *       'middag_five_minutes',
     *       CronHandler::dispatch(InvoiceService::class, 'syncFromStripe')
     *   );
     */
    public static function dispatch(ContainerInterface $container, string $serviceClass, string $method): Closure
    {
        return static function () use ($container, $serviceClass, $method): void {
            // Wire the framework PSR-3 logger from the container when the host has
            // not primed the bridge yet (cron may run before any web boot). Falls
            // back to error_log() inside LogSupport when no logger is available.
            LogSupport::primeFromContainer($container, 'wordpress', 'cron');

            try {
                if (!$container->has($serviceClass)) {
                    LogSupport::error('[MIDDAG Cron] Service not found: ' . $serviceClass);

                    return;
                }

                $service = $container->get($serviceClass);
                $service->{$method}();
            } catch (Throwable $throwable) {
                LogSupport::error(sprintf('[MIDDAG Cron] Error in %s::%s: %s', $serviceClass, $method, $throwable->getMessage()));
            }
        };
    }
}
