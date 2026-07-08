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

use Middag\Framework\Logging\LoggerFactory;
use Middag\WordPress\Logging\PhpErrorLogLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Bridge from the adapter's operational error sites to PSR-3 logging.
 *
 * Production code (cron dispatch, email send/render) reports operational
 * failures through this seam instead of calling `error_log()` directly, so the
 * platform/log coupling lives in one place. The host wires the framework's
 * PSR-3 logger once at boot via {@see self::setLogger()}; every subsequent
 * {@see self::error()}/{@see self::warning()}/{@see self::log()} call is routed
 * to it.
 *
 * When no logger has been wired yet — the bootstrap window before the
 * container/logger exists, or CLI/cron paths that never boot the kernel — the
 * bridge degrades to PHP's built-in `error_log()`. That is the ONLY intentional
 * `error_log()` fallback site in the adapter: losing operational output
 * entirely is worse than the fallback, and `error_log()` is a language builtin
 * that is always available.
 *
 * @internal
 */
final class LogSupport
{
    private static ?LoggerInterface $logger = null;

    /**
     * Wire the process-wide PSR-3 logger. Pass null to clear it (restores the
     * `error_log()` fallback). Called once by the host at boot; called by tests
     * to inject a spy.
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * The currently wired logger, or null when only the fallback is active.
     */
    public static function getLogger(): ?LoggerInterface
    {
        return self::$logger;
    }

    /**
     * Prime the bridge from the framework's logging service.
     *
     * The framework registers a {@see LoggerFactory} service but does NOT bind a
     * shared `Psr\Log\LoggerInterface` (logging is channel-based). This resolves
     * the factory from the container and wires the `(module, channel)` PSR-3
     * logger. The host composition root calls it once at boot so every adapter
     * error site — cron and web (email) alike — reaches the framework logger.
     *
     * No-op when already primed or when the container exposes no LoggerFactory
     * (the `error_log()` fallback then stays active). Returns whether a logger is
     * wired afterwards.
     *
     * This is the canonical, WooCommerce-like channel path: the `(module,
     * channel)` tuple selects the on-disk destination of the framework's
     * rotating file handler, and consumers/adapters pick their own tuple over
     * the same base (a zero-dep `error_log` fallback such as
     * {@see PhpErrorLogLogger} exists only for hosts
     * where no factory is wired).
     */
    public static function primeFromContainer(ContainerInterface $container, string $module = 'wordpress', string $channel = 'adapter'): bool
    {
        if (self::$logger instanceof LoggerInterface) {
            return true;
        }

        if (!$container->has(LoggerFactory::class)) {
            return false;
        }

        $factory = $container->get(LoggerFactory::class);
        if (!$factory instanceof LoggerFactory) {
            return false;
        }

        self::$logger = $factory->forChannel($module, $channel);

        return true;
    }

    /**
     * Log at error level. Routes to the wired PSR-3 logger, or falls back to
     * `error_log()` during the bootstrap window.
     *
     * @param array<string, mixed> $context
     */
    public static function error(string|Stringable $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Log at warning level. Routes to the wired PSR-3 logger, or falls back to
     * `error_log()` during the bootstrap window.
     *
     * @param array<string, mixed> $context
     */
    public static function warning(string|Stringable $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /**
     * Log at an arbitrary PSR-3 level. Routes to the wired logger when present;
     * otherwise writes to `error_log()` (the bootstrap-fallback site).
     *
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string|Stringable $message, array $context = []): void
    {
        $logger = self::$logger;

        if ($logger instanceof LoggerInterface) {
            $logger->log($level, $message, $context);

            return;
        }

        // Bootstrap fallback: no logger wired yet (intentional error_log site).
        error_log((string) $message);
    }
}
