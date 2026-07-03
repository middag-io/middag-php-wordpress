<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Bus;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\WordPress\Support\UserSupport;

/**
 * WordPress user context resolver.
 *
 * Resolves the current user ID via {@see UserSupport::currentUserId()}.
 * Returns null when no user is authenticated (e.g. WP-CLI, cron).
 */
final class WpUserContext implements UserContextResolverInterface
{
    public function getCurrentUserId(): ?int
    {
        $userId = UserSupport::currentUserId();

        return $userId > 0 ? $userId : null;
    }
}
