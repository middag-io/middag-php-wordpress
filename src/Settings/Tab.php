<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Settings;

/**
 * One tab of a settings page. Each tab registers under its own page slug
 * (`{page}-{tab}`) so WordPress renders only the active tab's sections.
 *
 * @api
 */
final readonly class Tab
{
    /**
     * @param non-empty-string $slug
     * @param list<Section>    $sections
     */
    public function __construct(
        public string $slug,
        public string $title,
        public array $sections,
    ) {}
}
