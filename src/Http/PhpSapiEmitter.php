<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http;

use Middag\WordPress\Http\Contract\ResponseEmitterInterface;

/**
 * Production {@see ResponseEmitterInterface}: the single home for the native
 * PHP/WordPress output primitives (`http_response_code`, `header`,
 * `wp_redirect`, `echo`, `exit`) that the adapter's HTTP pipeline used to inline
 * across InertiaAdapter and CsrfGuard. The `headers_sent()` guard lives here
 * once, so no caller repeats it.
 *
 * @internal
 */
final class PhpSapiEmitter implements ResponseEmitterInterface
{
    public function status(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    public function header(string $name, string $value): void
    {
        if (!headers_sent()) {
            header($name . ': ' . $value);
        }
    }

    public function redirect(string $url): void
    {
        wp_redirect($url);
    }

    public function write(string $body): void
    {
        echo $body;
    }

    public function terminate(): never
    {
        exit;
    }
}
