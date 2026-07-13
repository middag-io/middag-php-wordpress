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

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Support\UserSupport;
use WP_Error;
use WP_User;
use WP_User_Query;

/**
 * @api
 */
final class UserRepository
{
    /**
     * Find a user by ID.
     */
    public function findById(int $id): ?WP_User
    {
        $user = get_user_by('id', $id);

        return $user instanceof WP_User ? $user : null;
    }

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?WP_User
    {
        $user = get_user_by('email', $email);

        return $user instanceof WP_User ? $user : null;
    }

    /**
     * Find a user by login (username).
     */
    public function findByLogin(string $login): ?WP_User
    {
        $user = get_user_by('login', $login);

        return $user instanceof WP_User ? $user : null;
    }

    /**
     * Get current logged-in user.
     */
    public function getCurrentUser(): ?WP_User
    {
        return UserSupport::currentUser();
    }

    /**
     * Get current user ID (0 if not logged in).
     */
    public function getCurrentUserId(): int
    {
        return UserSupport::currentUserId();
    }

    /**
     * Check if current user has a capability.
     */
    public function currentUserCan(CapabilityInterface|string $capability): bool
    {
        return UserSupport::currentUserCan($capability);
    }

    /**
     * Query users with filters.
     */
    public function query(array $args = []): array
    {
        $defaults = [
            'number' => 20,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        $query = new WP_User_Query(array_merge($defaults, $args));

        return $query->get_results();
    }

    /**
     * Count users matching criteria.
     */
    public function count(array $args = []): int
    {
        $args['count_total'] = true;
        $args['number'] = 0;

        $query = new WP_User_Query($args);

        return $query->get_total();
    }

    /**
     * Search users by name or email.
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->query([
            'search' => sprintf('*%s*', $term),
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => $limit,
        ]);
    }

    /**
     * Get users by role.
     */
    public function findByRole(string $role, int $limit = -1): array
    {
        return $this->query([
            'role' => $role,
            'number' => $limit,
        ]);
    }

    /**
     * Create a new WordPress user.
     */
    public function create(string $email, string $password, array $userData = []): int|WP_Error
    {
        $username = $userData['user_login'] ?? $email;

        $data = array_merge([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => 'subscriber',
        ], $userData);

        return wp_insert_user($data);
    }

    /**
     * Update an existing user.
     */
    public function update(int $userId, array $data): int|WP_Error
    {
        $data['ID'] = $userId;

        return wp_update_user($data);
    }
}
