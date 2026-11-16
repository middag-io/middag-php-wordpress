<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics;

use InvalidArgumentException;
use WP_CLI;

/**
 * Registers the {@see DoctorCommand} with WP-CLI under a consumer-chosen root.
 *
 * The command root is configurable and defaults to the neutral library marker
 * (`wp middaglib doctor`), which names no product; a consumer passes its own
 * root (for example `acme` for `wp acme doctor`). Outside WP-CLI,
 * {@see register()} does nothing and returns false, so it is safe to call from
 * a plugin file on every request.
 *
 * Call it from the main plugin file, not from the kernel boot: the command has
 * to exist precisely when the kernel failed to boot. Several plugins of one
 * family may register the same root; WP-CLI keeps the last registration, which
 * is harmless as long as they share the diagnostics filter name and therefore
 * print the same report.
 *
 * @api
 */
final readonly class DoctorCommandRegistrar
{
    public const string DEFAULT_ROOT = 'middaglib';

    public const string SUBCOMMAND = 'doctor';

    /**
     * @throws InvalidArgumentException when the root is empty or not a single WP-CLI word
     */
    public function __construct(
        private DoctorCommand $command,
        private string $root = self::DEFAULT_ROOT,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $root) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid WP-CLI command root "%s": use lowercase letters, digits and dashes.', $root));
        }
    }

    /**
     * Full command name as typed after `wp` (for example `acme doctor`).
     */
    public function commandName(): string
    {
        return $this->root . ' ' . self::SUBCOMMAND;
    }

    /**
     * Add the command to WP-CLI; returns false when not running under WP-CLI.
     */
    public function register(): bool
    {
        if (!$this->isWpCli()) {
            return false;
        }

        WP_CLI::add_command($this->commandName(), $this->command, [
            'shortdesc' => 'Print the boot report of the registered packages and exit non-zero when one is broken.',
        ]);

        return true;
    }

    private function isWpCli(): bool
    {
        return defined('WP_CLI') && constant('WP_CLI') === true && class_exists(WP_CLI::class);
    }
}
