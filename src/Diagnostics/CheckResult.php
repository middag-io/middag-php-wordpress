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
 * Immutable outcome of one diagnostic check.
 *
 * @api
 */
final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
        public CheckScope $scope = CheckScope::Boot,
    ) {}

    public static function pass(string $name, string $message, CheckScope $scope = CheckScope::Boot): self
    {
        return new self($name, CheckStatus::Pass, $message, $scope);
    }

    public static function warn(string $name, string $message, CheckScope $scope = CheckScope::Boot): self
    {
        return new self($name, CheckStatus::Warn, $message, $scope);
    }

    public static function fail(string $name, string $message, CheckScope $scope = CheckScope::Boot): self
    {
        return new self($name, CheckStatus::Fail, $message, $scope);
    }

    /**
     * @return array{name: string, status: string, scope: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'scope' => $this->scope->value,
            'message' => $this->message,
        ];
    }
}
