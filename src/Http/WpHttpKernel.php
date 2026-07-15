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

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\WordPress\Security\Attribute\Nonce;
use Middag\WordPress\Support\SecuritySupport;
use ReflectionClass;
use ReflectionMethod;

/**
 * WordPress-flavored HTTP kernel.
 *
 * Extends the framework's host-agnostic {@see HttpKernel} (route matching,
 * middleware pipeline, `#[Auth]`/`#[Middleware]` attributes, argument
 * resolution) to apply the WordPress-specific {@see Nonce} attribute after
 * the framework `Auth` attribute has been processed — the counterpart of the
 * Moodle adapter's `MoodleHttpKernel`/`#[Sesskey]`.
 *
 * @api
 */
final class WpHttpKernel extends HttpKernel
{
    /**
     * Reads the `#[Nonce]` attribute (method wins over class) and rejects the
     * request when the nonce it names is missing or invalid.
     *
     * The nonce value is resolved from the request field named by the
     * attribute (default `_wpnonce`), falling back to the `X-WP-Nonce`
     * header. Fail-closed: a required nonce that cannot be verified aborts
     * dispatch with a 403.
     *
     * @throws MiddagAuthorizationException
     */
    protected function applyPlatformAuth(ControllerInterface $controller, string $method): void
    {
        $attrs = (new ReflectionMethod($controller, $method))->getAttributes(Nonce::class);

        if ($attrs === []) {
            $attrs = (new ReflectionClass($controller))->getAttributes(Nonce::class);
        }

        if ($attrs === []) {
            return;
        }

        /** @var Nonce $nonce */
        $nonce = $attrs[0]->newInstance();

        if (!$nonce->require) {
            return;
        }

        if (!SecuritySupport::verifyNonce($this->nonceFromRequest($nonce->param), $nonce->action)) {
            throw new MiddagAuthorizationException('Invalid or missing nonce for action: ' . $nonce->action);
        }
    }

    /**
     * The nonce value carried by the current request: the named request field
     * first (WordPress form convention), then the `X-WP-Nonce` header (REST /
     * XHR convention). Superglobals are read here because this hook runs at
     * the host boundary, mirroring how `check_admin_referer()` resolves them.
     */
    private function nonceFromRequest(string $param): ?string
    {
        $value = $_REQUEST[$param] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
