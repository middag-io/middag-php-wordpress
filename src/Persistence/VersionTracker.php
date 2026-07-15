<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Persistence;

use Middag\Framework\Database\Contract\VersionTrackerInterface;

/**
 * WordPress options-backed schema version tracker.
 *
 * The WordPress counterpart of the Moodle adapter's `get_config`-backed
 * tracker: each lib registers its own tracker with a distinct option name so
 * schema versions are stored independently (e.g. `middag_core_schema_version`,
 * `middag_framework_schema_version`).
 *
 * The option is written with `autoload = false`: the version is only read
 * during install/upgrade flows, never on the request hot path.
 *
 * @api
 */
class VersionTracker implements VersionTrackerInterface
{
    public function __construct(
        private readonly string $optionName,
    ) {}

    public function getVersion(): int
    {
        return (int) get_option($this->optionName, 0);
    }

    public function setVersion(int $version): void
    {
        update_option($this->optionName, $version, false);
    }
}
