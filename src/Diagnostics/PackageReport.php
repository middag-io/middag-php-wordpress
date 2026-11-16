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

use Middag\WordPress\Diagnostics\Enum\CheckStatus;

/**
 * Collected check results of one registered package.
 *
 * @api
 */
final readonly class PackageReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(
        public string $name,
        public string $version,
        public array $results,
    ) {}

    /**
     * Worst status among the package's results.
     */
    public function status(): CheckStatus
    {
        return array_reduce(
            $this->results,
            static fn (CheckStatus $carry, CheckResult $result): CheckStatus => $carry->worst($result->status),
            CheckStatus::Pass,
        );
    }

    /**
     * @return array{name: string, version: string, status: string, checks: list<array{name: string, status: string, scope: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'status' => $this->status()->value,
            'checks' => array_map(static fn (CheckResult $result): array => $result->toArray(), $this->results),
        ];
    }
}
