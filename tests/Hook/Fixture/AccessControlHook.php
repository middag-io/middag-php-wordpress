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

use Middag\WordPress\Hook\Contract\HookInterface;

/**
 * Discovery fixture: implements HookInterface but its filename ends in `Hook`,
 * not `Hooks`. Named after the real class that silently never registered while
 * discovery matched on filename suffix.
 *
 * @internal
 */
final class AccessControlHook implements HookInterface
{
    public function register(): void
    {
        $GLOBALS['__middag_test_registered_hooks'][] = spl_object_hash($this) . ':' . self::class;
    }
}
