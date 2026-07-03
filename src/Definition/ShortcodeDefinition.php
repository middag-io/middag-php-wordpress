<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Definition;

/**
 * Declarative shortcode: tag + render callback, registered by
 * {@see DefinitionRegistrar}. The callback receives the standard WP shortcode
 * signature `(array|string $atts, ?string $content, string $tag)` and returns
 * the markup (shortcodes must return, never echo).
 *
 * @api
 */
final readonly class ShortcodeDefinition
{
    /**
     * @param non-empty-string $tag
     */
    public function __construct(
        public string $tag,
        public mixed $render,
    ) {}
}
