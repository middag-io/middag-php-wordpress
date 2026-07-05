<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\User;

/**
 * @api
 */
final class UserMeta
{
    /**
     * Get a single user meta value.
     */
    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        $value = get_user_meta($userId, $key, true);

        return $value !== '' ? $value : $default;
    }

    /**
     * Get all meta for a user.
     */
    public function getAll(int $userId): array
    {
        $raw = get_user_meta($userId);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $key => $values) {
            if (str_starts_with($key, 'wp_')) {
                continue;
            }
            if (str_starts_with($key, '_')) {
                continue;
            }
            $result[$key] = maybe_unserialize($values[0] ?? '');
        }

        return $result;
    }

    /**
     * Set a user meta value.
     */
    public function set(int $userId, string $key, mixed $value): void
    {
        update_user_meta($userId, $key, $value);
    }

    /**
     * Set multiple meta values at once.
     */
    public function setBatch(int $userId, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                delete_user_meta($userId, $key);
            } else {
                update_user_meta($userId, $key, $value);
            }
        }
    }

    /**
     * Delete a user meta key.
     */
    public function delete(int $userId, string $key): void
    {
        delete_user_meta($userId, $key);
    }

    /**
     * Check if a user meta key exists.
     */
    public function has(int $userId, string $key): bool
    {
        return metadata_exists('user', $userId, $key);
    }
}
