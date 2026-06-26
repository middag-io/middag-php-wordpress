<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Middleware;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Middag\WordPress\Support\UserSupport;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_User;

final class AuthMiddleware
{
    public const ALGORITHM = 'RS256';

    public const ACCESS_TOKEN_TTL = 86400; // 24 hours

    public const REFRESH_TOKEN_TTL = 604800; // 7 days

    private const LAST_LOGOUT_META = 'middag_last_logout';

    private const REFRESH_TOKEN_HASH_META = 'middag_refresh_token_hash';

    private static string $issuer = 'middag';

    public static function setIssuer(string $issuer): void
    {
        self::$issuer = $issuer;
    }

    /**
     * Get the authenticated user from a REST request (JWT or WP session).
     */
    public static function getCurrentUser(?WP_REST_Request $request = null): WP_Error|WP_User|null
    {
        $userId = self::getCurrentUserId($request);

        if (is_wp_error($userId)) {
            return $userId;
        }

        if (is_int($userId)) {
            return get_user_by('ID', $userId) ?: null;
        }

        return null;
    }

    /**
     * Get the authenticated user ID from a REST request.
     */
    public static function getCurrentUserId(?WP_REST_Request $request = null): int|WP_Error|null
    {
        // Check WordPress session first
        $userId = UserSupport::currentUserId();
        if ($userId > 0) {
            return $userId;
        }

        // Check JWT token from request
        if ($request instanceof WP_REST_Request) {
            $user = self::getUserFromToken($request);
            if (is_wp_error($user)) {
                return $user;
            }

            return (int) $user->ID;
        }

        return null;
    }

    /**
     * Extract and validate JWT from Authorization header.
     */
    public static function getUserFromToken(WP_REST_Request $request): WP_Error|WP_User
    {
        $authHeader = $request->get_header('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return new WP_Error('token_missing', 'Token not provided.', ['status' => 401]);
        }

        return self::validateAccessToken($matches[1]);
    }

    /**
     * Check if request has valid authentication.
     */
    public static function isAuthenticated(WP_REST_Request $request): true|WP_Error
    {
        $user = self::getCurrentUser($request);

        if (is_wp_error($user)) {
            return $user;
        }

        if ($user instanceof WP_User) {
            return true;
        }

        return new WP_Error('unauthorized', 'User not authenticated.', ['status' => 401]);
    }

    /**
     * Check if authenticated user is an administrator.
     */
    public static function isAdmin(?WP_REST_Request $request = null): bool
    {
        $user = self::getCurrentUser($request);

        return $user instanceof WP_User && in_array('administrator', $user->roles, true);
    }

    /**
     * Validate an access token and return the associated user.
     * JWT payload uses top-level claims.
     */
    public static function validateAccessToken(string $token): WP_Error|WP_User
    {
        try {
            $publicKey = self::resolveEnv('PUBLIC_KEY');
            if (!$publicKey) {
                return new WP_Error('config_error', 'Public key not found.', ['status' => 500]);
            }

            $decoded = JWT::decode($token, new Key($publicKey, self::ALGORITHM));

            // Validate issuer
            if (($decoded->iss ?? '') !== self::$issuer) {
                return new WP_Error('token_invalid', 'Invalid issuer.', ['status' => 401]);
            }

            // Top-level sub claim.
            $userId = $decoded->sub ?? null;
            if (!$userId) {
                return new WP_Error('token_invalid', 'Invalid token.', ['status' => 401]);
            }

            $user = get_user_by('ID', (int) $userId);
            if (!$user) {
                return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
            }

            // Check token was issued after last logout
            $lastLogout = (int) get_user_meta($user->ID, self::LAST_LOGOUT_META, true);
            if (($decoded->iat ?? 0) < $lastLogout) {
                return new WP_Error('token_revoked', 'Token revoked.', ['status' => 401]);
            }

            return $user;
        } catch (ExpiredException) {
            return new WP_Error('token_expired', 'Token expired.', ['status' => 401]);
        } catch (Exception) {
            return new WP_Error('token_invalid', 'Invalid token.', ['status' => 401]);
        }
    }

    /**
     * Generate JWT access + refresh token pair for a user.
     *
     * @param array<string> $roles  Collaborator roles in the active org
     * @param array<string> $scopes Feature scopes granted
     */
    public static function generateTokens(
        WP_User $user,
        ?int $orgId = null,
        array $roles = [],
        array $scopes = [],
    ): array {
        return [
            'access_token' => self::generateAccessToken($user, $orgId, $roles, $scopes),
            'refresh_token' => self::generateRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL,
        ];
    }

    /**
     * Validate a refresh token with replay detection.
     *
     * If the stored hash does not match the provided token, all tokens are
     * revoked (replay attack). Caller must call revokeRefreshToken() before
     * issuing a new pair (rotation).
     */
    public static function validateRefreshToken(string $token): ?WP_User
    {
        try {
            $publicKey = self::resolveEnv('PUBLIC_KEY');
            if (!$publicKey) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($publicKey, self::ALGORITHM));

            $userId = $decoded->sub ?? null;
            if (!$userId) {
                return null;
            }

            $user = get_user_by('ID', (int) $userId);
            if (!$user) {
                return null;
            }

            // Replay detection via stored hash
            $storedHash = (string) get_user_meta($user->ID, self::REFRESH_TOKEN_HASH_META, true);
            $tokenHash = hash('sha256', $token);

            if ($storedHash === '') {
                // Already consumed or never stored → replay attack
                self::revokeTokens($user);

                return null;
            }

            if (!hash_equals($storedHash, $tokenHash)) {
                // Hash mismatch → replay attack → revoke all
                self::revokeTokens($user);

                return null;
            }

            return $user;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Invalidate the current refresh token (call before issuing a new pair).
     */
    public static function revokeRefreshToken(WP_User $user): void
    {
        delete_user_meta($user->ID, self::REFRESH_TOKEN_HASH_META);
    }

    /**
     * Revoke all tokens for a user: sets last logout + clears refresh hash.
     */
    public static function revokeTokens(WP_User $user): void
    {
        update_user_meta($user->ID, self::LAST_LOGOUT_META, time());
        delete_user_meta($user->ID, self::REFRESH_TOKEN_HASH_META);
    }

    /**
     * Resolve an environment variable by name.
     *
     * Checks MDGA_ prefixed first (framework convention), then bare name,
     * then $_SERVER. Returns null if not found.
     */
    private static function resolveEnv(string $key): ?string
    {
        $val = getenv('MDGA_' . strtoupper($key));
        if ($val !== false && $val !== '') {
            return $val;
        }

        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }

        return $_SERVER[$key] ?? null;
    }

    /**
     * Generate an access token with top-level claims.
     *
     * @param array<string> $roles
     * @param array<string> $scopes
     */
    private static function generateAccessToken(
        WP_User $user,
        ?int $orgId = null,
        array $roles = [],
        array $scopes = [],
    ): string {
        $privateKey = self::resolveEnv('PRIVATE_KEY');
        if (!$privateKey) {
            throw new RuntimeException('Private key not found.');
        }

        $now = time();

        return JWT::encode([
            'sub' => $user->ID,
            'org' => $orgId,
            'roles' => $roles,
            'scopes' => $scopes,
            'iss' => self::$issuer,
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
        ], $privateKey, self::ALGORITHM);
    }

    /**
     * Generate a refresh token and store its SHA-256 hash for rotation/replay detection.
     */
    private static function generateRefreshToken(WP_User $user): string
    {
        $privateKey = self::resolveEnv('PRIVATE_KEY');
        if (!$privateKey) {
            throw new RuntimeException('Private key not found.');
        }

        $now = time();
        $token = JWT::encode([
            'sub' => $user->ID,
            'iss' => self::$issuer,
            'iat' => $now,
            'exp' => $now + self::REFRESH_TOKEN_TTL,
        ], $privateKey, self::ALGORITHM);

        update_user_meta($user->ID, self::REFRESH_TOKEN_HASH_META, hash('sha256', $token));

        return $token;
    }
}
