<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Response;

use WP_REST_Response;

/**
 * Standardized REST response envelope.
 *
 * All responses always include: success, data, meta, message, errors.
 *
 * 7 canonical error codes:
 *   VALIDATION_ERROR (422), AUTHENTICATION_ERROR (401), AUTHORIZATION_ERROR (403),
 *   NOT_FOUND (404), CONFLICT (409), RATE_LIMIT (429), INTERNAL_ERROR (500)
 *
 * @api
 */
final class RestResponse
{
    public const ERR_VALIDATION = 'VALIDATION_ERROR';

    public const ERR_AUTHENTICATION = 'AUTHENTICATION_ERROR';

    public const ERR_AUTHORIZATION = 'AUTHORIZATION_ERROR';

    public const ERR_NOT_FOUND = 'NOT_FOUND';

    public const ERR_CONFLICT = 'CONFLICT';

    public const ERR_RATE_LIMIT = 'RATE_LIMIT';

    public const ERR_INTERNAL = 'INTERNAL_ERROR';

    // -------------------------------------------------------------------------
    // Success responses
    // -------------------------------------------------------------------------

    public static function success(mixed $data = null, int $status = 200, ?string $message = null): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => null,
            'message' => $message,
            'errors' => null,
        ], $status);
    }

    public static function paginated(array $data, int $total, int $perPage, int $currentPage): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
            'message' => null,
            'errors' => null,
        ], 200);
    }

    public static function created(mixed $data = null, ?string $message = null): WP_REST_Response
    {
        return self::success($data, 201, $message);
    }

    public static function noContent(): WP_REST_Response
    {
        return new WP_REST_Response(null, 204);
    }

    // -------------------------------------------------------------------------
    // Error responses
    // -------------------------------------------------------------------------

    /**
     * Generic error. Prefer the semantic helpers below.
     *
     * @param null|array<string, mixed> $errors Additional error context
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        ?array $errors = null,
    ): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'data' => null,
            'meta' => null,
            'message' => $message,
            'errors' => array_merge(['code' => $code], $errors ?? []),
        ], $status);
    }

    /** 401 — Missing, invalid, or expired token. */
    public static function unauthorized(string $message = 'Unauthorized.', string $detail = ''): WP_REST_Response
    {
        return self::error(self::ERR_AUTHENTICATION, $message, 401, $detail !== '' ? ['detail' => $detail] : null);
    }

    /** 403 — Authenticated but no permission. */
    public static function forbidden(string $message = 'Access denied.', string $detail = ''): WP_REST_Response
    {
        return self::error(self::ERR_AUTHORIZATION, $message, 403, $detail !== '' ? ['detail' => $detail] : null);
    }

    /** 404 — Resource not found. */
    public static function notFound(string $message = 'Resource not found.'): WP_REST_Response
    {
        return self::error(self::ERR_NOT_FOUND, $message, 404);
    }

    /** 409 — State conflict. */
    public static function conflict(string $message, string $detail = ''): WP_REST_Response
    {
        return self::error(self::ERR_CONFLICT, $message, 409, $detail !== '' ? ['detail' => $detail] : null);
    }

    /** 422 — Input validation failed. */
    public static function validationError(array $fields, string $message = 'Validation failed.'): WP_REST_Response
    {
        return self::error(self::ERR_VALIDATION, $message, 422, ['fields' => $fields]);
    }

    /** 429 — Rate limit exceeded. Sets Retry-After header. */
    public static function rateLimit(string $message = 'Rate limit exceeded.', int $retryAfter = 60): WP_REST_Response
    {
        $response = self::error(
            self::ERR_RATE_LIMIT,
            $message,
            429,
            ['detail' => sprintf('Try again in %d seconds.', $retryAfter)]
        );
        $response->header('Retry-After', (string) $retryAfter);

        return $response;
    }

    /** 500 — Unexpected internal error. */
    public static function internalError(string $message = 'Internal server error.'): WP_REST_Response
    {
        return self::error(self::ERR_INTERNAL, $message, 500);
    }
}
