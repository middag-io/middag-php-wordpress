<?php

declare(strict_types=1);

/**
 * PHPStan-only stub for the Action Scheduler enqueue API.
 *
 * php-stubs/wordpress-stubs covers WordPress core only; Action Scheduler is a
 * separate library (bundled with WooCommerce) so its global functions are
 * unknown to a standalone static analysis. This file is listed under
 * `scanFiles` in .phpstan.neon — parsed for symbols, never executed, so it
 * carries no runtime weight and never collides with the real definitions.
 *
 * @see https://actionscheduler.org/api/
 */

/**
 * @param array<int, mixed> $args
 */
function as_enqueue_async_action(string $hook, array $args = [], string $group = '', bool $unique = false, int $priority = 10): int
{
    return 0;
}
