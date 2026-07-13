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

use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use WP_Error;
use WP_REST_Request;
use WP_User;

/**
 * Resolves the WordPress user behind an inbound REST request.
 *
 * This is the injected seam a controller's permission callbacks depend on, in
 * place of the old static auth middleware. The library ships a WordPress-session
 * binding ({@see WpSessionAuthenticator}); a product
 * that also accepts API bearer tokens composes its own binding (e.g. one backed
 * by a JWT token service) and injects that instead — the library itself carries
 * no product token logic.
 *
 * @api
 */
interface RequestAuthenticatorInterface
{
    /**
     * Resolve the user behind the request.
     *
     * @return null|WP_Error|WP_User a WP_User when authenticated; a WP_Error when
     *                               a presented credential was rejected (the
     *                               error is surfaced to the caller as-is); null
     *                               when the request is simply anonymous
     */
    public function resolveUser(WP_REST_Request $request): WP_Error|WP_User|null;

    /**
     * Whether the user behind the request is a site administrator.
     */
    public function isAdmin(WP_REST_Request $request): bool;
}
