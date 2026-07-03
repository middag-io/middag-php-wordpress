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
 * A titled group of fields inside a settings tab, mapped 1:1 onto a WordPress
 * settings section.
 *
 * @api
 */
final readonly class Section
{
    /**
     * @param non-empty-string $id
     * @param list<Field>      $fields
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $fields,
        public string $description = '',
    ) {}
}
