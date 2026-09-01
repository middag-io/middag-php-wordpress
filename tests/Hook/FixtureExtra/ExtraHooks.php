<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook\FixtureExtra;

use Middag\WordPress\Hook\Contract\HookInterface;

/**
 * Discovery fixture living in a SECOND root, under its own namespace: proves
 * the registrar scans every configured path, not a single directory.
 *
 * @internal
 */
final class ExtraHooks implements HookInterface
{
    public function register(): void
    {
        $GLOBALS['__middag_test_registered_hooks'][] = spl_object_hash($this) . ':' . self::class;
    }
}
