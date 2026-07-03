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

/**
 * CRUD operations for wp_postmeta.
 *
 * Use this for custom post type (CPT) metadata.
 */
final class PostMetaRepository
{
    public function getAllForPost(int $postId): array
    {
        // Prime the meta cache
        update_postmeta_cache([$postId]);

        $raw = get_post_meta($postId);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $key => $values) {
            // Skip internal WordPress meta
            if (str_starts_with($key, '_')) {
                continue;
            }
            $result[$key] = maybe_unserialize($values[0] ?? '');
        }

        return $result;
    }

    public function get(int $postId, string $key, mixed $default = null): mixed
    {
        $value = get_post_meta($postId, $key, true);

        return $value !== '' ? $value : $default;
    }

    public function set(int $postId, string $key, mixed $value): void
    {
        update_post_meta($postId, $key, $value);
    }

    public function setBatch(int $postId, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                delete_post_meta($postId, $key);
            } else {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    public function delete(int $postId, string $key): void
    {
        delete_post_meta($postId, $key);
    }

    public function deleteBatch(int $postId, array $keys): void
    {
        foreach ($keys as $key) {
            delete_post_meta($postId, $key);
        }
    }

    /**
     * Prime meta cache for multiple posts at once (prevents N+1).
     *
     * @param int[] $postIds
     */
    public function primeCache(array $postIds): void
    {
        if ($postIds !== []) {
            update_postmeta_cache($postIds);
        }
    }
}
