<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook\FixtureNeedy;

use Middag\WordPress\Hook\Contract\HookInterface;
use stdClass;

/**
 * Discovery fixture: a hook the container does not know that cannot be built
 * without arguments. The registrar must say so by name instead of letting a
 * bare ArgumentCountError escape from inside the boot.
 *
 * @internal
 */
final readonly class NeedyHooks implements HookInterface
{
    public function __construct(private stdClass $dependency) {}

    public function register(): void
    {
        // The dependency is read here on purpose: an unused promoted property
        // gets stripped by Rector, and stripping it removes the very thing this
        // fixture exists to prove — a hook that cannot be built without help.
        $GLOBALS['__middag_test_registered_hooks'][] = spl_object_hash($this->dependency) . ':' . self::class;
    }
}
