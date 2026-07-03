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
 * Declarative custom post type: name it once, get sensible labels and args;
 * `args` overrides anything the convention derives.
 *
 * @api
 */
final readonly class PostTypeDefinition
{
    /**
     * @param non-empty-string     $slug
     * @param array<string, mixed> $args overrides merged over the derived defaults
     */
    public function __construct(
        public string $slug,
        public string $singular,
        public string $plural,
        public array $args = [],
    ) {}

    /**
     * The full register_post_type() argument array: derived defaults with the
     * definition's overrides merged on top.
     *
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $defaults = [
            'labels' => [
                'name' => $this->plural,
                'singular_name' => $this->singular,
                'add_new_item' => 'Add New ' . $this->singular,
                'edit_item' => 'Edit ' . $this->singular,
                'all_items' => 'All ' . $this->plural,
                'not_found' => 'No ' . strtolower($this->plural) . ' found.',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title'],
        ];

        return array_replace($defaults, $this->args);
    }
}
