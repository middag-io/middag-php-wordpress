<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\Post;

use Middag\WordPress\Persistence\QueryBuilder;
use WP_Post;

/**
 * Repository for wp_posts queries using the WP_Query-based QueryBuilder.
 *
 * Use this for custom post type (CPT) persistence (D4: wp_posts support).
 *
 * @api
 */
class PostRepository
{
    public function __construct(
        private readonly string $postType,
    ) {}

    public function find(int $id): ?WP_Post
    {
        return $this->newQuery()->find($id);
    }

    public function first(): ?WP_Post
    {
        return $this->newQuery()->first();
    }

    /**
     * @return WP_Post[]
     */
    public function get(): array
    {
        return $this->newQuery()->get();
    }

    /**
     * @return array{data: WP_Post[], total: int, per_page: int, current_page: int, pages: int}
     */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        return $this->newQuery()
            ->page($page)
            ->limit($perPage)
            ->paginate();
    }

    public function count(): int
    {
        return $this->newQuery()->count();
    }

    /**
     * Get a fresh QueryBuilder instance scoped to this post type.
     */
    protected function newQuery(): QueryBuilder
    {
        return (new QueryBuilder())->postType($this->postType);
    }
}
