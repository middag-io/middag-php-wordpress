<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Auth;

use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Support\UserSupport;
use WP_REST_Request;
use WP_User;

/**
 * The batteries-included {@see RequestAuthenticatorInterface}: resolves the user
 * from the established WordPress session (cookie auth) only.
 *
 * Bearer-token / JWT resolution deliberately lives OUTSIDE the library — that is
 * product logic. A product that exposes a token-authenticated API composes an
 * authenticator that first tries a token, then falls back to this one, and
 * injects the composite into its controllers. This keeps the OSS library free of
 * any product credential logic.
 *
 * @api
 */
final class WpSessionAuthenticator implements RequestAuthenticatorInterface
{
    public function resolveUser(WP_REST_Request $request): ?WP_User
    {
        $userId = UserSupport::currentUserId();
        if ($userId <= 0) {
            return null;
        }

        $user = get_user_by('ID', $userId);

        return $user instanceof WP_User ? $user : null;
    }

    public function isAdmin(WP_REST_Request $request): bool
    {
        $user = $this->resolveUser($request);

        return $user instanceof WP_User && in_array('administrator', $user->roles, true);
    }
}
