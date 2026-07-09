<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Middleware;

use Firebase\JWT\JWT;
use Middag\WordPress\Http\Middleware\AuthMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_User;

/**
 * Behavioural JWT coverage for the host auth middleware: token issuing,
 * validation (issuer/expiry/revocation), refresh rotation with replay
 * detection, and the session-vs-token resolution order — all against a real
 * RSA keypair generated per test class.
 *
 * @internal
 */
#[CoversClass(AuthMiddleware::class)]
final class AuthMiddlewareTest extends TestCase
{
    private static string $privateKey;

    private static string $publicKey;

    private static string $opensslConfigPath;

    public static function setUpBeforeClass(): void
    {
        // Self-contained openssl config: keygen must not depend on the
        // calling shell's OPENSSL_CONF (broken/missing on some hosts breaks
        // openssl_pkey_new/export with no portable fallback otherwise).
        self::$opensslConfigPath = tempnam(sys_get_temp_dir(), 'mdga-openssl-');
        file_put_contents(self::$opensslConfigPath, "[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n");
        $configArgs = ['config' => self::$opensslConfigPath];

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ] + $configArgs);
        self::assertNotFalse($resource, 'openssl must be able to generate a test RSA keypair');

        openssl_pkey_export($resource, $privateKey, null, $configArgs);
        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);

        self::$privateKey = $privateKey;
        self::$publicKey = $details['key'];
    }

    public static function tearDownAfterClass(): void
    {
        putenv('MDGA_PRIVATE_KEY');
        putenv('MDGA_PUBLIC_KEY');
        unlink(self::$opensslConfigPath);
    }

    protected function setUp(): void
    {
        putenv('MDGA_PRIVATE_KEY=' . self::$privateKey);
        putenv('MDGA_PUBLIC_KEY=' . self::$publicKey);

        $GLOBALS['__wp_test_users_by'] = [];
        $GLOBALS['__wp_test_metadata'] = [];
        $GLOBALS['__wp_test_user_id'] = 0;
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_users_by'],
            $GLOBALS['__wp_test_metadata'],
            $GLOBALS['__wp_test_user_id'],
        );
    }

    #[Test]
    public function generatedAccessTokenValidatesBackToTheUser(): void
    {
        $user = $this->registerUser(7);

        $tokens = AuthMiddleware::generateTokens($user, 3, ['manager'], ['items:read']);

        self::assertSame('Bearer', $tokens['token_type']);
        self::assertSame(AuthMiddleware::ACCESS_TOKEN_TTL, $tokens['expires_in']);

        $validated = AuthMiddleware::validateAccessToken($tokens['access_token']);
        self::assertSame($user, $validated);
    }

    #[Test]
    public function garbageTokensAreRejectedAsInvalid(): void
    {
        $error = AuthMiddleware::validateAccessToken('not-a-jwt');

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('token_invalid', $error->get_error_code());
    }

    #[Test]
    public function aForeignIssuerIsRejected(): void
    {
        $this->registerUser(7);
        $now = time();
        $token = JWT::encode(
            ['sub' => 7, 'iss' => 'evil', 'iat' => $now, 'exp' => $now + 60],
            self::$privateKey,
            AuthMiddleware::ALGORITHM,
        );

        $error = AuthMiddleware::validateAccessToken($token);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('token_invalid', $error->get_error_code());
    }

    #[Test]
    public function anExpiredTokenIsRejectedAsExpired(): void
    {
        $this->registerUser(7);
        $now = time();
        $token = JWT::encode(
            ['sub' => 7, 'iss' => 'middag', 'iat' => $now - 200, 'exp' => $now - 100],
            self::$privateKey,
            AuthMiddleware::ALGORITHM,
        );

        $error = AuthMiddleware::validateAccessToken($token);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('token_expired', $error->get_error_code());
    }

    #[Test]
    public function aTokenForAnUnknownUserIsRejected(): void
    {
        $now = time();
        $token = JWT::encode(
            ['sub' => 999, 'iss' => 'middag', 'iat' => $now, 'exp' => $now + 60],
            self::$privateKey,
            AuthMiddleware::ALGORITHM,
        );

        $error = AuthMiddleware::validateAccessToken($token);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('user_not_found', $error->get_error_code());
    }

    #[Test]
    public function tokensIssuedBeforeTheLastLogoutAreRevoked(): void
    {
        $user = $this->registerUser(7);
        $tokens = AuthMiddleware::generateTokens($user);

        update_user_meta(7, 'middag_last_logout', time() + 10);

        $error = AuthMiddleware::validateAccessToken($tokens['access_token']);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('token_revoked', $error->get_error_code());
    }

    #[Test]
    public function missingPublicKeyIsAConfigError(): void
    {
        putenv('MDGA_PUBLIC_KEY');

        try {
            $error = AuthMiddleware::validateAccessToken('anything');

            self::assertInstanceOf(WP_Error::class, $error);
            self::assertSame('config_error', $error->get_error_code());
        } finally {
            putenv('MDGA_PUBLIC_KEY=' . self::$publicKey);
        }
    }

    #[Test]
    public function missingPrivateKeyAbortsTokenGeneration(): void
    {
        $user = $this->registerUser(7);
        putenv('MDGA_PRIVATE_KEY');

        try {
            $this->expectException(RuntimeException::class);
            AuthMiddleware::generateTokens($user);
        } finally {
            putenv('MDGA_PRIVATE_KEY=' . self::$privateKey);
        }
    }

    #[Test]
    public function getUserFromTokenRequiresABearerHeader(): void
    {
        $request = new WP_REST_Request();

        $error = AuthMiddleware::getUserFromToken($request);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('token_missing', $error->get_error_code());
    }

    #[Test]
    public function getUserFromTokenResolvesABearerToken(): void
    {
        $user = $this->registerUser(7);
        $tokens = AuthMiddleware::generateTokens($user);

        $request = new WP_REST_Request();
        $request->set_header('Authorization', 'Bearer ' . $tokens['access_token']);

        self::assertSame($user, AuthMiddleware::getUserFromToken($request));
    }

    #[Test]
    public function theWordPressSessionWinsOverTheToken(): void
    {
        $user = $this->registerUser(9);
        $GLOBALS['__wp_test_user_id'] = 9;

        self::assertSame(9, AuthMiddleware::getCurrentUserId());
        self::assertSame($user, AuthMiddleware::getCurrentUser());
        self::assertTrue(AuthMiddleware::isAuthenticated(new WP_REST_Request()));
    }

    #[Test]
    public function anonymousRequestsWithoutATokenAreNotAuthenticated(): void
    {
        $result = AuthMiddleware::isAuthenticated(new WP_REST_Request());

        self::assertInstanceOf(WP_Error::class, $result);
    }

    #[Test]
    public function isAdminReflectsTheAdministratorRole(): void
    {
        $admin = $this->registerUser(9, ['administrator']);
        $GLOBALS['__wp_test_user_id'] = 9;

        self::assertTrue(AuthMiddleware::isAdmin());

        $admin->roles = ['subscriber'];
        self::assertFalse(AuthMiddleware::isAdmin());
    }

    #[Test]
    public function refreshTokenRoundTripsUntilRotated(): void
    {
        $user = $this->registerUser(7);
        $tokens = AuthMiddleware::generateTokens($user);

        self::assertSame($user, AuthMiddleware::validateRefreshToken($tokens['refresh_token']));

        // Rotation: consuming the token deletes the stored hash; presenting the
        // same token again is a replay and revokes everything.
        AuthMiddleware::revokeRefreshToken($user);

        self::assertNull(AuthMiddleware::validateRefreshToken($tokens['refresh_token']));
        self::assertNotSame(
            '',
            (string) get_user_meta(7, 'middag_last_logout', true),
            'replay detection revokes all tokens (last logout is stamped)',
        );
    }

    #[Test]
    public function aTamperedRefreshHashTriggersFullRevocation(): void
    {
        $user = $this->registerUser(7);
        $tokens = AuthMiddleware::generateTokens($user);

        update_user_meta(7, 'middag_refresh_token_hash', hash('sha256', 'someone-elses-token'));

        self::assertNull(AuthMiddleware::validateRefreshToken($tokens['refresh_token']));
        self::assertFalse(
            metadata_exists('user', 7, 'middag_refresh_token_hash'),
            'revocation clears the stored refresh hash',
        );
    }

    /**
     * @param array<int, string> $roles
     */
    private function registerUser(int $id, array $roles = []): WP_User
    {
        $user = new WP_User($id);
        $user->roles = $roles;
        $GLOBALS['__wp_test_users_by']['id'][(string) $id] = $user;

        return $user;
    }
}
