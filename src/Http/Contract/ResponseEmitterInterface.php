<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Contract;

/**
 * Injectable sink for the HTTP response side-effects the WordPress adapter
 * performs outside the PSR-15 kernel — the status code, headers, redirects, the
 * response body, and terminating the request.
 *
 * Centralising these primitives behind one seam lets the pipeline classes
 * (InertiaAdapter, CsrfGuard, ...) build their responses as pure data and route
 * the actual emit through an injected implementation: the production
 * PhpSapiEmitter, or a recording fake in tests. This makes the previously
 * untestable exit/echo/header paths assertable in-process.
 *
 * @api
 */
interface ResponseEmitterInterface
{
    /**
     * Set the HTTP status code. Implementations guard against headers already
     * having been sent.
     */
    public function status(int $code): void;

    /**
     * Send a single response header as a `Name: value` pair.
     */
    public function header(string $name, string $value): void;

    /**
     * Issue a redirect to the given URL (WordPress-aware in production).
     */
    public function redirect(string $url): void;

    /**
     * Emit a chunk of the response body verbatim. Callers escape/encode first —
     * this is the raw output boundary, not an escaping layer.
     */
    public function write(string $body): void;

    /**
     * Terminate the request. Production exits the process; test fakes throw so
     * the terminating branch stays assertable.
     */
    public function terminate(): never;
}
