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

use Closure;
use Middag\WordPress\Diagnostics\Contract\DiagnosticCheckInterface;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\Enum\CheckStatus;

/**
 * A diagnostic check written as a closure.
 *
 * The closure returns `true` (pass), `false` (fail with the default message),
 * a string (fail with that message) or a full {@see CheckResult}. Example:
 *
 *     new CallbackCheck('options', CheckScope::Boot, static fn (): bool|string =>
 *         get_option('acme_settings') !== false ?: 'acme_settings option is missing');
 *
 * @api
 */
final readonly class CallbackCheck implements DiagnosticCheckInterface
{
    /**
     * @param Closure(): (bool|CheckResult|string) $callback
     */
    public function __construct(
        private string $name,
        private CheckScope $scope,
        private Closure $callback,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function scope(): CheckScope
    {
        return $this->scope;
    }

    public function run(): CheckResult
    {
        $outcome = ($this->callback)();

        if ($outcome instanceof CheckResult) {
            return $outcome;
        }

        if ($outcome === true) {
            return new CheckResult($this->name, CheckStatus::Pass, 'ok', $this->scope);
        }

        $message = is_string($outcome) && $outcome !== '' ? $outcome : 'check returned false';

        return new CheckResult($this->name, CheckStatus::Fail, $message, $this->scope);
    }
}
