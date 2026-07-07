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
 * Host-neutral shape for a WordPress term.
 *
 * @api
 */
final readonly class TermDto
{
    public function __construct(
        public int $id,
        public string $taxonomy,
        public string $slug,
        public string $name,
        public ?int $parentId = null,
    ) {}
}
