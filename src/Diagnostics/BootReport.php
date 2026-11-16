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

use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\Enum\CheckStatus;

/**
 * Self-check of the packages registered in this WordPress request.
 *
 * Collected after autoload and boot, it answers per package: did the
 * autoloader resolve it (and from where), did its kernel boot, which version
 * is it, and what did its own checks find. Report-level issues come on top: a
 * package the caller expected that never registered (a plugin that failed to
 * load cannot declare itself, so absence is the strongest signal of all), an
 * invalid registration, or no package at all.
 *
 * Scope: the built-in checks prove boot, not render. Unless at least one
 * {@see CheckScope::Render} check ran, {@see coversRender()} is false and the
 * rendered report says so, so a green report is never read as "the interface
 * works".
 *
 * @api
 */
final readonly class BootReport
{
    public const int EXIT_HEALTHY = 0;

    public const int EXIT_BROKEN = 1;

    /**
     * @param list<PackageReport> $packages
     * @param list<string>        $issues   report-level problems, each one a failure
     */
    public function __construct(
        public array $packages,
        public array $issues = [],
    ) {}

    /**
     * Inspect every probe and compare the registered names with the expected
     * ones.
     *
     * @param list<PackageProbe> $probes
     * @param list<string>       $expected package names that must be registered
     * @param int                $invalid  registrations that were not a {@see PackageProbe}
     */
    public static function collect(array $probes, array $expected = [], int $invalid = 0): self
    {
        $inspector = new PackageInspector();
        $packages = array_map(static fn (PackageProbe $probe): PackageReport => $inspector->inspect($probe), $probes);

        return new self($packages, self::issuesFor($probes, $expected, $invalid));
    }

    /**
     * Worst status across packages; any report-level issue is a failure.
     */
    public function status(): CheckStatus
    {
        $status = $this->issues === [] ? CheckStatus::Pass : CheckStatus::Fail;

        foreach ($this->packages as $package) {
            $status = $status->worst($package->status());
        }

        return $status;
    }

    /**
     * Process exit code for a health gate: non-zero when broken, and also on
     * warnings when strict.
     */
    public function exitCode(bool $strict = false): int
    {
        $status = $this->status();

        if ($status === CheckStatus::Fail || ($strict && $status === CheckStatus::Warn)) {
            return self::EXIT_BROKEN;
        }

        return self::EXIT_HEALTHY;
    }

    /**
     * Whether at least one render check ran; when false the report proves
     * boot only.
     */
    public function coversRender(): bool
    {
        foreach ($this->packages as $package) {
            foreach ($package->results as $result) {
                if ($result->scope === CheckScope::Render) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{status: string, covers: list<string>, issues: list<string>, packages: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status()->value,
            'covers' => $this->coversRender()
                ? [CheckScope::Boot->value, CheckScope::Render->value]
                : [CheckScope::Boot->value],
            'issues' => $this->issues,
            'packages' => array_map(static fn (PackageReport $package): array => $package->toArray(), $this->packages),
        ];
    }

    /**
     * @param list<PackageProbe> $probes
     * @param list<string>       $expected
     *
     * @return list<string>
     */
    private static function issuesFor(array $probes, array $expected, int $invalid): array
    {
        $issues = [];

        if ($probes === []) {
            $issues[] = 'no package registered for diagnostics';
        }

        if ($invalid > 0) {
            $issues[] = sprintf('%d registration(s) ignored: not a package probe', $invalid);
        }

        $names = array_map(static fn (PackageProbe $probe): string => $probe->name, $probes);

        foreach (array_count_values($names) as $name => $count) {
            if ($count > 1) {
                $issues[] = sprintf('package "%s" registered %d times', $name, $count);
            }
        }

        foreach (array_values(array_unique($expected)) as $name) {
            if (!in_array($name, $names, true)) {
                $issues[] = sprintf('expected package "%s" is not registered (did it load?)', $name);
            }
        }

        return $issues;
    }
}
