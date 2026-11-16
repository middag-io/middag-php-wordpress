<?php

declare(strict_types=1);

/**
 * PHPStan-only stub for the slice of the WP-CLI API the adapter calls.
 *
 * php-stubs/wordpress-stubs covers WordPress core only, and
 * php-stubs/wp-cli-stubs does not accept the wordpress-stubs 7.x series this
 * package analyses against, so the few WP-CLI symbols used under `src/` are
 * declared here. This file is listed under `scanFiles` in .phpstan.neon —
 * parsed for symbols, never executed, so it carries no runtime weight and
 * never collides with the real definitions. Runtime use is guarded with
 * `defined('WP_CLI')` and `class_exists(WP_CLI::class)`.
 *
 * @see https://make.wordpress.org/cli/handbook/references/internal-api/
 */

class WP_CLI
{
    /**
     * @param callable|object|string $callable
     * @param array<string, mixed>   $args
     */
    public static function add_command(string $name, mixed $callable, array $args = []): bool
    {
        return true;
    }

    public static function line(string $message = ''): void {}

    /**
     * @return never
     */
    public static function halt(int $return_code): void
    {
        exit($return_code);
    }
}
