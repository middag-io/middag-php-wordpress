<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\Comment;

/**
 * Host-neutral shape for a WordPress comment.
 *
 * @api
 */
final readonly class CommentDto
{
    public function __construct(
        public int $id,
        public int $postId,
        public string $authorName,
        public string $authorEmail,
        public string $content,
        public string $status,
    ) {}
}
