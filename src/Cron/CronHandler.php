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
     *       CronInterval::FiveMinutes,
     *       CronHandler::dispatch($container, InvoiceService::class, 'syncFromStripe')
     *   );
     */
    public static function dispatch(ContainerInterface $container, string $serviceClass, string $method): Closure
    {
        return static function () use ($container, $serviceClass, $method): void {
            // Resolve this cron run's logger from the container: the framework
            // channel logger when wired, else the error_log() fallback. Stateless
            // — no shared process-wide logger slot to collide on.
            $logger = LogSupport::resolve($container, 'wordpress', 'cron');

            try {
                if (!$container->has($serviceClass)) {
                    $logger->error('[MIDDAG Cron] Service not found: ' . $serviceClass);

                    return;
                }

                $service = $container->get($serviceClass);
                $service->{$method}();
            } catch (Throwable $throwable) {
                $logger->error(sprintf('[MIDDAG Cron] Error in %s::%s: %s', $serviceClass, $method, $throwable->getMessage()));
            }
        };
    }
}
