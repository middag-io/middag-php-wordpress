<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Controller;

use Middag\WordPress\Http\Contract\RestControllerInterface;
use Middag\WordPress\Http\Middleware\AuthMiddleware;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WordPress\Support\SanitizeSupport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

abstract class BaseController implements RestControllerInterface
{
    /**
     * Permission callback for authenticated routes.
     */
    public function permissionCheck(WP_REST_Request $request): true|WP_Error
    {
        // CORS preflight
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }

        // Authentication
        $auth = AuthMiddleware::isAuthenticated($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        return true;
    }

    /**
     * Permission callback for admin-only routes.
     */
    public function adminPermissionCheck(WP_REST_Request $request): true|WP_Error
    {
        $auth = AuthMiddleware::isAuthenticated($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        if (!AuthMiddleware::isAdmin($request)) {
            return new WP_Error('forbidden', 'Access restricted to administrators.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Permission callback for public routes (no auth required).
     */
    public function publicPermissionCheck(WP_REST_Request $request): true
    {
        return true;
    }

    /**
     * Get the authenticated user from the request.
     */
    protected function getUser(WP_REST_Request $request): ?WP_User
    {
        $user = AuthMiddleware::getCurrentUser($request);

        return $user instanceof WP_User ? $user : null;
    }

    /**
     * Parse JSON body from request.
     */
    protected function getBody(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();

        return is_array($body) ? $body : [];
    }

    /**
     * Read a single-line scalar from the request, sanitized through the Sanitize
     * seam (strips tags + control whitespace). Use this for inbound text fields
     * instead of reading `get_param()` raw at the controller boundary.
     */
    protected function sanitizedText(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);

        return is_string($value) ? SanitizeSupport::text($value) : $default;
    }

    /**
     * Read a key/slug scalar from the request, sanitized through the Sanitize
     * seam (lowercase; alphanumerics, dashes, underscores only).
     */
    protected function sanitizedKey(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);

        return is_string($value) ? SanitizeSupport::key($value) : $default;
    }

    /**
     * Read an email scalar from the request, sanitized through the Sanitize
     * seam. Returns the default when the value is missing or not a valid email.
     */
    protected function sanitizedEmail(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);
        if (!is_string($value)) {
            return $default;
        }

        $email = SanitizeSupport::email($value);

        return $email === '' ? $default : $email;
    }

    /**
     * Read a multi-line text scalar (e.g. a textarea body) from the request,
     * sanitized through the Sanitize seam, preserving newlines.
     */
    protected function sanitizedTextarea(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);

        return is_string($value) ? SanitizeSupport::textarea($value) : $default;
    }

    /**
     * Read an HTML scalar from the request, filtered through the Sanitize seam
     * against the WordPress post-content allowlist (`wp_kses_post`).
     */
    protected function sanitizedHtml(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);

        return is_string($value) ? SanitizeSupport::ksesPost($value) : $default;
    }

    /**
     * Validate required fields are present in data.
     */
    protected function validateRequired(array $data, array $fields): ?WP_REST_Response
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[$field] = sprintf('The %s field is required.', $field);
            }
        }

        if ($missing !== []) {
            return RestResponse::validationError($missing);
        }

        return null;
    }

    /**
     * Register a REST route helper.
     */
    protected function route(
        string $namespace,
        string $path,
        string $method,
        callable $callback,
        ?callable $permission = null,
    ): void {
        register_rest_route($namespace, $path, [
            'methods' => $method,
            'callback' => $callback,
            'permission_callback' => $permission ?? [$this, 'permissionCheck'],
        ]);
    }
}
