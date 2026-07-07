<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\Taxonomy;

/**
 * Host-neutral shape for a WordPress taxonomy.
 *
 * @api
 */
final readonly class TaxonomyDto
{
    /**
     * @param list<string> $objectTypes
     */
    public function __construct(
        public string $slug,
        public string $singular,
        public string $plural,
        public array $objectTypes = [],
        public bool $hierarchical = false,
    ) {}
}
