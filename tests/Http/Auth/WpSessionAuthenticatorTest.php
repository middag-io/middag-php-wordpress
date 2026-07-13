<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Auth;

use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_User;

/**
 * The WP-session authenticator resolves the current cookie-session user and
 * reports admin status; it carries no token logic (that is product code).
 *
 * @internal
 */
#[CoversClass(WpSessionAuthenticator::class)]
final class WpSessionAuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_user_id'] = 0;
        $GLOBALS['__wp_test_users_by'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_user_id'], $GLOBALS['__wp_test_users_by']);
    }

    #[Test]
    public function resolveUserReturnsTheSessionUser(): void
    {
        $this->loginAs(9, ['subscriber']);

        $user = (new WpSessionAuthenticator())->resolveUser(new WP_REST_Request());

        self::assertInstanceOf(WP_User::class, $user);
        self::assertSame(9, $user->ID);
    }

    #[Test]
    public function resolveUserReturnsNullWhenNoSession(): void
    {
        self::assertNull((new WpSessionAuthenticator())->resolveUser(new WP_REST_Request()));
    }

    #[Test]
    public function resolveUserReturnsNullWhenTheSessionIdHasNoUserRecord(): void
    {
        // A session id is set but the user row is gone (deleted mid-session).
        $GLOBALS['__wp_test_user_id'] = 42;

        self::assertNull((new WpSessionAuthenticator())->resolveUser(new WP_REST_Request()));
    }

    #[Test]
    public function isAdminIsTrueOnlyForTheAdministratorRole(): void
    {
        $auth = new WpSessionAuthenticator();

        $this->loginAs(7, ['administrator']);
        self::assertTrue($auth->isAdmin(new WP_REST_Request()));

        $this->loginAs(8, ['editor']);
        self::assertFalse($auth->isAdmin(new WP_REST_Request()));
    }

    #[Test]
    public function isAdminIsFalseWhenAnonymous(): void
    {
        self::assertFalse((new WpSessionAuthenticator())->isAdmin(new WP_REST_Request()));
    }

    /**
     * @param array<int, string> $roles
     */
    private function loginAs(int $id, array $roles): void
    {
        $user = new WP_User($id);
        $user->roles = $roles;
        $GLOBALS['__wp_test_user_id'] = $id;
        $GLOBALS['__wp_test_users_by']['id'][(string) $id] = $user;
    }
}
