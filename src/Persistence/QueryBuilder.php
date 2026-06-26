<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Persistence;

use WP_Post;
use WP_Query;

final class QueryBuilder
{
    private array $args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 20,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [],
        'no_found_rows' => false,
    ];

    public function postType(string $type): self
    {
        $clone = clone $this;
        $clone->args['post_type'] = $type;

        return $clone;
    }

    public function status(array|string $status): self
    {
        $clone = clone $this;
        $clone->args['post_status'] = $status;

        return $clone;
    }

    public function metaWhere(string $key, mixed $value, string $compare = '=', string $type = 'CHAR'): self
    {
        $clone = clone $this;
        $clone->args['meta_query'][] = [
            'key' => $key,
            'value' => $value,
            'compare' => $compare,
            'type' => $type,
        ];

        return $clone;
    }

    public function metaWhereExists(string $key): self
    {
        $clone = clone $this;
        $clone->args['meta_query'][] = [
            'key' => $key,
            'compare' => 'EXISTS',
        ];

        return $clone;
    }

    public function metaRelation(string $relation): self
    {
        $clone = clone $this;
        $clone->args['meta_query']['relation'] = $relation;

        return $clone;
    }

    public function author(int $userId): self
    {
        $clone = clone $this;
        $clone->args['author'] = $userId;

        return $clone;
    }

    public function parent(int $parentId): self
    {
        $clone = clone $this;
        $clone->args['post_parent'] = $parentId;

        return $clone;
    }

    public function search(string $term): self
    {
        $clone = clone $this;
        $clone->args['s'] = $term;

        return $clone;
    }

    public function whereIn(array $ids): self
    {
        $clone = clone $this;
        $clone->args['post__in'] = $ids;

        return $clone;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $clone = clone $this;
        $clone->args['orderby'] = $field;
        $clone->args['order'] = strtoupper($direction);

        return $clone;
    }

    public function orderByMeta(string $metaKey, string $direction = 'ASC', string $type = 'CHAR'): self
    {
        $clone = clone $this;
        $clone->args['meta_key'] = $metaKey;
        $clone->args['orderby'] = 'meta_value';
        $clone->args['order'] = strtoupper($direction);
        if ($type !== 'CHAR') {
            $clone->args['orderby'] = 'meta_value_num';
        }

        return $clone;
    }

    public function limit(int $count): self
    {
        $clone = clone $this;
        $clone->args['posts_per_page'] = $count;

        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->args['offset'] = $offset;

        return $clone;
    }

    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->args['paged'] = $page;

        return $clone;
    }

    public function withFoundRows(): self
    {
        $clone = clone $this;
        $clone->args['no_found_rows'] = false;

        return $clone;
    }

    public function noFoundRows(): self
    {
        $clone = clone $this;
        $clone->args['no_found_rows'] = true;

        return $clone;
    }

    public function get(): array
    {
        $query = new WP_Query($this->args);

        return $query->posts;
    }

    public function paginate(): array
    {
        $args = $this->args;
        $args['no_found_rows'] = false;

        $query = new WP_Query($args);

        return [
            'data' => $query->posts,
            'total' => $query->found_posts,
            'per_page' => (int) ($args['posts_per_page'] ?? 20),
            'current_page' => (int) ($args['paged'] ?? 1),
            'pages' => $query->max_num_pages,
        ];
    }

    public function first(): ?WP_Post
    {
        $clone = clone $this;
        $clone->args['posts_per_page'] = 1;
        $clone->args['no_found_rows'] = true;
        $results = $clone->get();

        return $results[0] ?? null;
    }

    public function count(): int
    {
        $args = $this->args;
        $args['posts_per_page'] = -1;
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;

        $query = new WP_Query($args);

        return $query->post_count;
    }

    public function ids(): array
    {
        $args = $this->args;
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;

        $query = new WP_Query($args);

        return $query->posts;
    }

    public function find(int $id): ?WP_Post
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== $this->args['post_type']) {
            return null;
        }

        return $post;
    }

    public function toArgs(): array
    {
        return $this->args;
    }
}
