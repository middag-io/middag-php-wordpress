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
 * Declarative taxonomy bound to one or more post types.
 *
 * @api
 */
final readonly class TaxonomyDefinition
{
    /**
     * @param non-empty-string     $slug
     * @param list<string>         $objectTypes post type slugs the taxonomy attaches to
     * @param array<string, mixed> $args        overrides merged over the derived defaults
     */
    public function __construct(
        public string $slug,
        public array $objectTypes,
        public string $singular,
        public string $plural,
        public array $args = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $defaults = [
            'labels' => [
                'name' => $this->plural,
                'singular_name' => $this->singular,
            ],
            'public' => false,
            'show_ui' => true,
            'hierarchical' => false,
        ];

        return array_replace($defaults, $this->args);
    }
}
