<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics\Contract;

use Middag\WordPress\Diagnostics\CheckResult;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\PackageProbe;

/**
 * Extension point for package- and host-level checks in the boot report.
 *
 * A package attaches its own checks to its {@see PackageProbe}
 * beyond the built-in autoload, kernel and version checks: a required table
 * exists, a scheduled hook is queued, or, with {@see CheckScope::Render}, an
 * admin page still comes out with `<!DOCTYPE`, `<head>`, `<body>` and the core
 * script bundles. The report runs every check lazily, when it is collected,
 * and turns an exception thrown by {@see run()} into a failed result, so a
 * check never has to catch its own errors.
 *
 * A check must be cheap and read-only: the report is a health gate run by
 * pipelines, not a repair tool.
 *
 * @api
 */
interface DiagnosticCheckInterface
{
    /**
     * Short, stable label shown in the report (for example `schema`).
     */
    public function name(): string;

    /**
     * The layer this check proves.
     */
    public function scope(): CheckScope;

    /**
     * Run the check now and describe the outcome.
     */
    public function run(): CheckResult;
}
