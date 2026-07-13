<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Security\Enum;

use Middag\WordPress\Support\CapabilitySupport;
use Middag\WordPress\Support\UserSupport;

/**
 * Single conversion of a `string|CapabilityInterface` into the plain WordPress
 * capability string the platform APIs require.
 *
 * Used at the three authorization boundaries that accept a typed capability for
 * ergonomics — the read seam ({@see UserSupport}), the
 * write seam ({@see CapabilitySupport}), and the
 * declarative value objects/registrars — so the enum→string step lives in one
 * place instead of being re-inlined at every call site.
 *
 * @internal
 */
trait NormalizesCapability
{
    private static function capabilityString(CapabilityInterface|string $capability): string
    {
        return $capability instanceof CapabilityInterface ? $capability->toString() : $capability;
    }
}
