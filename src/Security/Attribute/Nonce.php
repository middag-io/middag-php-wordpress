<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Security\Attribute;

use Attribute;

/**
 * Declares that a controller action (or every action of a controller) must
 * carry a valid WordPress nonce for the given action string.
 *
 * The WordPress counterpart of the Moodle adapter's `#[Sesskey]` attribute:
 * `WpHttpKernel` reads it (method wins over class) after the framework
 * `#[Auth]` attribute has been processed, resolves the nonce value from the
 * request (`$param` request field, then the `X-WP-Nonce` header) and rejects
 * the request with a 403 when verification fails.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Nonce
{
    public function __construct(
        public string $action,
        public string $param = '_wpnonce',
        public bool $require = true,
    ) {}
}
