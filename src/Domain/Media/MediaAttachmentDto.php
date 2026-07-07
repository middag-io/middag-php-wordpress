<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\Media;

/**
 * Host-neutral shape for a WordPress media attachment.
 *
 * @api
 */
final readonly class MediaAttachmentDto
{
    public function __construct(
        public int $id,
        public string $url,
        public string $mimeType,
        public string $title = '',
        public string $altText = '',
    ) {}
}
