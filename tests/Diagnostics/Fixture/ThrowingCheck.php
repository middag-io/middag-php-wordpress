<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Diagnostics\Fixture;

use Middag\WordPress\Diagnostics\CheckResult;
use Middag\WordPress\Diagnostics\Contract\DiagnosticCheckInterface;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use RuntimeException;

/**
 * A render check whose run() throws.
 *
 * @internal
 */
final class ThrowingCheck implements DiagnosticCheckInterface
{
    public function name(): string
    {
        return 'admin-shell';
    }

    public function scope(): CheckScope
    {
        return CheckScope::Render;
    }

    public function run(): CheckResult
    {
        throw new RuntimeException('page has no <head>');
    }
}
