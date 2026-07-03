<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook\Fixture\Admin;

use Middag\WordPress\Hook\Contract\HookInterface;

/**
 * Discovery fixture: a nested `*Hooks` class — proves the FQCN is derived
 * from the path relative to the hook directory (subnamespace preserved).
 *
 * @internal
 */
final class MenuHooks implements HookInterface
{
    public function register(): void
    {
        $GLOBALS['__middag_test_registered_hooks'][] = spl_object_hash($this) . ':' . self::class;
    }
}
