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

use WP_REST_Request;
use WP_REST_Response;

/**
 * Per-route middleware that wraps a single WordPress REST controller action.
 *
 * The WP-REST-native counterpart of the framework's
 * {@see Middag\Framework\Http\Contract\RouteMiddlewareInterface}: that contract
 * is HttpFoundation-native and composes with the admin
 * {@see Middag\WordPress\Http\WpHttpKernel}; this one speaks `WP_REST_Request` /
 * `WP_REST_Response` so it composes directly with the {@see WpRestKernel}
 * dispatch without a per-route HttpFoundation bridge, and a denial stays in the
 * product's REST envelope (e.g. {@see Middag\WordPress\Http\Response\RestResponse::forbidden()}).
 *
 * Declare middleware on an action (or controller class) with the
 * {@see Middag\Framework\Http\Attribute\Middleware} attribute — the same
 * attribute the admin surface uses; only the interface the kernel validates
 * against differs per surface. The {@see WpRestKernel} resolves each entry from
 * the container and composes them around the action, outermost first (class-level
 * before method-level). A middleware may inspect or decorate the request,
 * short-circuit by returning its own {@see WP_REST_Response} (the scope/RBAC or
 * rate-limit gate), or call `$next($request)` to continue the chain and
 * post-process the result.
 *
 * The action's arguments are resolved when the action runs — the innermost link
 * in the chain — so a request rejected by an outer middleware never resolves the
 * action arguments nor touches the controller.
 *
 * @api
 */
interface RestRouteMiddlewareInterface
{
    /**
     * Process the request, optionally delegating to the rest of the chain.
     *
     * @param callable(WP_REST_Request): WP_REST_Response $next the next middleware, or the action
     */
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response;
}
