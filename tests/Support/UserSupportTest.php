<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Security\Enum\WooCommerceCapability;
use Middag\WordPress\Security\Enum\WpCapability;
use Middag\WordPress\Support\UserSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_User;

/**
 * @internal
 */
#[CoversClass(UserSupport::class)]
final class UserSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_caps'] = [];
        unset($GLOBALS['__wp_test_user_id'], $GLOBALS['__wp_test_current_user']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_caps'],
            $GLOBALS['__wp_test_user_id'],
            $GLOBALS['__wp_test_current_user'],
        );
    }

    #[Test]
    public function currentUserIdReturnsZeroWhenAnonymous(): void
    {
        self::assertSame(0, UserSupport::currentUserId());
    }

    #[Test]
    public function currentUserIdReturnsTheLoggedInId(): void
    {
        $GLOBALS['__wp_test_user_id'] = 42;

        self::assertSame(42, UserSupport::currentUserId());
    }

    #[Test]
    public function currentUserCanReflectsTheCapabilityMap(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        self::assertTrue(UserSupport::currentUserCan('manage_options'));
        self::assertFalse(UserSupport::currentUserCan('edit_posts'));
    }

    #[Test]
    public function currentUserCanAcceptsATypedCapability(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        // The typed capability normalizes to the same string the map is keyed by.
        self::assertTrue(UserSupport::currentUserCan(WpCapability::ManageOptions));
        self::assertFalse(UserSupport::currentUserCan(WpCapability::EditPosts));
        self::assertFalse(UserSupport::currentUserCan(WooCommerceCapability::ManageWooCommerce));
    }

    #[Test]
    public function currentUserReturnsNullWhenAnonymous(): void
    {
        $GLOBALS['__wp_test_current_user'] = new WP_User(0);

        self::assertNull(UserSupport::currentUser());
    }

    #[Test]
    public function currentUserReturnsTheUserWhenAuthenticated(): void
    {
        $GLOBALS['__wp_test_current_user'] = new WP_User(7);

        $user = UserSupport::currentUser();
        self::assertInstanceOf(WP_User::class, $user);
        self::assertSame(7, $user->ID);
    }
}
