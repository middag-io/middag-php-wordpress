<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Security;

use Middag\Framework\Http\Middleware\VerifyCsrfMiddleware;
use Middag\WordPress\Http\Middleware\AuthMiddleware;
use Middag\WordPress\Support\SecuritySupport;

/**
 * CSRF guard for the WordPress admin Inertia pipeline.
 *
 * The admin SPA dispatches through a single `middag_inertia` action over
 * WordPress hooks — not the framework's PSR-15 kernel — so the framework's
 * {@see VerifyCsrfMiddleware} (419 + Symfony
 * tokens) does not apply here. This guard enforces the *native* WordPress nonce
 * instead, via {@see SecuritySupport}.
 *
 * Contract:
 *  - Only state-changing verbs (POST/PUT/PATCH/DELETE) are guarded; safe verbs
 *    pass straight through.
 *  - The nonce is read from the `X-WP-Nonce` request header first, then from a
 *    `_wpnonce` body field (classic form posts).
 *  - On a missing/invalid nonce the request is rejected with HTTP 403 and an
 *    Inertia-aware JSON envelope (deliberately *not* 419, which the Inertia
 *    client treats as a session-expired auto-reload).
 *  - The REST/JWT surface ({@see AuthMiddleware})
 *    is untouched: WordPress already nonce-protects `wp-json` via `X-WP-Nonce`
 *    and bearer tokens are not cookie-bound.
 *
 * Decision/extraction/payload methods are pure and unit-tested; {@see enforce()}
 * is the thin superglobal-reading exit path (mirrors {@see InertiaAdapter}'s
 * untested `exit` branches).
 *
 * @api
 */
final class CsrfGuard
{
    /** The single nonce action shared with the admin SPA. */
    public const NONCE_ACTION = 'middag_inertia';

    /** HTTP status for a missing/invalid nonce (WordPress-native, not Laravel's 419). */
    public const STATUS_FORBIDDEN = 403;

    /** Superglobal key for the `X-WP-Nonce` request header. */
    private const HEADER_KEY = 'HTTP_X_WP_NONCE';

    /** Body field name for the classic-form nonce fallback. */
    private const BODY_KEY = '_wpnonce';

    /** @var list<string> */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Whether the HTTP method changes server state and therefore requires a nonce.
     */
    public static function isMutating(string $method): bool
    {
        return in_array(strtoupper($method), self::UNSAFE_METHODS, true);
    }

    /**
     * Pull the nonce from the request: `X-WP-Nonce` header first, then the
     * `_wpnonce` body field. Returns null when neither carries a usable value.
     *
     * @param array<string, mixed> $server superglobal-shaped server vars (e.g. $_SERVER)
     * @param array<string, mixed> $body   parsed request body (e.g. $_POST)
     */
    public static function extractNonce(array $server, array $body): ?string
    {
        $header = $server[self::HEADER_KEY] ?? null;
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $field = $body[self::BODY_KEY] ?? null;
        if (is_string($field) && $field !== '') {
            return $field;
        }

        return null;
    }

    /**
     * Decide whether a request may proceed: safe verbs always pass; mutating
     * verbs require a nonce valid for {@see NONCE_ACTION}.
     */
    public static function isValidRequest(string $method, ?string $nonce): bool
    {
        if (!self::isMutating($method)) {
            return true;
        }

        return SecuritySupport::verifyNonce($nonce, self::NONCE_ACTION);
    }

    /**
     * The Inertia-aware rejection envelope sent with a 403.
     *
     * @return array{message: string, error: string}
     */
    public static function failurePayload(): array
    {
        return [
            'message' => 'Security check failed: missing or invalid nonce.',
            'error' => 'csrf_check_failed',
        ];
    }

    /**
     * Guard the current request, reading method/nonce from PHP superglobals.
     * No-op for safe verbs and valid mutating requests; otherwise emits the
     * 403 envelope and exits.
     *
     * Thin glue around the pure methods above — intentionally not unit-tested
     * (it reads superglobals and terminates the request, like
     * {@see InertiaAdapter::sendJson()}).
     */
    public static function enforce(): void
    {
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        /** @var array<string, mixed> $body */
        $body = $_POST;

        $method = is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET';
        $nonce = self::extractNonce($server, $body);

        if (self::isValidRequest($method, $nonce)) {
            return;
        }

        self::reject();
    }

    /**
     * Emit the 403 envelope and terminate. Thin, untested exit path.
     */
    private static function reject(): never
    {
        if (!headers_sent()) {
            http_response_code(self::STATUS_FORBIDDEN);
            header('Content-Type: application/json');
        }

        echo wp_json_encode(self::failurePayload(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        exit;
    }
}
