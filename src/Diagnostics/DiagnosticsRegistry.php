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
use Middag\WordPress\Support\HookSupport;

/**
 * Registration surface where packages declare themselves to the boot report.
 *
 * The registry keeps no state of its own: registrations ride on a WordPress
 * filter, so every plugin and theme in the request that uses the same filter
 * name meets in one report even though each of them boots its own container
 * and kernel. Each package builds its own registry instance with the filter
 * name its family agreed on; there is no static registry to collide on.
 *
 * The default filter name is the neutral library marker `middaglib_diagnostics`,
 * which names no product: a consumer passes its own (for example
 * `acme_diagnostics`). Third-party code can also contribute directly:
 *
 *     add_filter('acme_diagnostics', static fn (array $probes): array =>
 *         [...$probes, new PackageProbe('acme-addon', '1.2.0')]);
 *
 * @api
 */
final readonly class DiagnosticsRegistry
{
    public const string DEFAULT_FILTER = 'middaglib_diagnostics';

    /**
     * @throws InvalidArgumentException when the filter name is empty
     */
    public function __construct(
        private string $filterName = self::DEFAULT_FILTER,
    ) {
        if (trim($filterName) === '') {
            throw new InvalidArgumentException('The diagnostics filter name must not be empty.');
        }
    }

    public function filterName(): string
    {
        return $this->filterName;
    }

    /**
     * Declare a package to the report. Call it from the main plugin file
     * before the kernel boots, so a failed boot still shows up as a failed
     * check instead of a missing package.
     */
    public function register(PackageProbe $probe): void
    {
        HookSupport::addFilter(
            $this->filterName,
            static fn (mixed $probes): array => [...(is_array($probes) ? array_values($probes) : []), $probe],
        );
    }

    /**
     * Every registration on the filter, split into valid probes and the
     * number of entries that were not a {@see PackageProbe}.
     *
     * @return array{probes: list<PackageProbe>, invalid: int}
     */
    public function collect(): array
    {
        $raw = HookSupport::applyFilters($this->filterName, []);

        if (!is_array($raw)) {
            return ['probes' => [], 'invalid' => 1];
        }

        $probes = array_values(array_filter($raw, static fn (mixed $entry): bool => $entry instanceof PackageProbe));

        return ['probes' => $probes, 'invalid' => count($raw) - count($probes)];
    }

    /**
     * Collect the report for the current request.
     *
     * @param list<string> $expected package names that must be registered
     */
    public function report(array $expected = []): BootReport
    {
        $collected = $this->collect();

        return BootReport::collect($collected['probes'], $expected, $collected['invalid']);
    }
}
