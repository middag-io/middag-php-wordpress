<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook\Fixture;

/**
 * Discovery fixture: filename does not end in `Hooks`, so the registrar must
 * skip it even though it lives inside the hook directory.
 *
 * @internal
 */
final class NotDiscoveredService
{
    public function register(): void
    {
        $GLOBALS['__middag_test_registered_hooks'][] = self::class;
    }
}
