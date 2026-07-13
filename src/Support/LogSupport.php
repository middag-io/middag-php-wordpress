<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Support;

use Middag\Framework\Logging\ErrorLogFallbackLogger;
use Middag\Framework\Logging\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless resolver from a host container to the PSR-3 logger the adapter's
 * operational sites (cron dispatch, email send/render) should report through.
 *
 * Resolution order:
 *  1. An explicit `Psr\Log\LoggerInterface` bound in the container — the host's
 *     chosen logger wins verbatim (also the seam tests inject a spy through).
 *  2. The framework's channel logger — `LoggerFactory::forChannel($module,
 *     $channel)` when a {@see LoggerFactory} is bound. This is the canonical,
 *     WooCommerce-like path: the `(module, channel)` tuple selects the on-disk
 *     destination of the framework's rotating file handler.
 *  3. The zero-dependency {@see ErrorLogFallbackLogger} — the bootstrap window
 *     and hosts that wired neither; losing operational output entirely is worse
 *     than the `error_log()` fallback.
 *
 * No process-wide state: two host plugins in the same request each resolve their
 * own logger from their own container, so the old static first-wins slot (one
 * plugin's logger silently serving another plugin's error sites) is gone.
 *
 * @internal
 */
final class LogSupport
{
    private function __construct() {}

    /**
     * Resolve the logger for an adapter `(module, channel)` tuple. Never returns
     * null — callers always get a usable PSR-3 logger (fallback included).
     */
    public static function resolve(
        ContainerInterface $container,
        string $module = 'wordpress',
        string $channel = 'adapter',
    ): LoggerInterface {
        if ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
            if ($logger instanceof LoggerInterface) {
                return $logger;
            }
        }

        if ($container->has(LoggerFactory::class)) {
            $factory = $container->get(LoggerFactory::class);
            if ($factory instanceof LoggerFactory) {
                return $factory->forChannel($module, $channel);
            }
        }

        return new ErrorLogFallbackLogger($channel);
    }
}
