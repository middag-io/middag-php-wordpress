<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Infrastructure\Bus;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\WordPress\Infrastructure\Bus\WpUserContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the WpUserContext class.
 *
 * Uses the get_current_user_id() stub which reads from $GLOBALS['__wp_test_user_id'].
 *
 * @internal
 *
 * @coversNothing
 */
final class WpUserContextTest extends TestCase
{
    private WpUserContext $context;

    protected function setUp(): void
    {
        $this->context = new WpUserContext();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_user_id']);
    }

    // -------------------------------------------------------------------------
    // getCurrentUserId()
    // -------------------------------------------------------------------------

    #[Test]
    public function returnsUserIdWhenUserIsLoggedIn(): void
    {
        $GLOBALS['__wp_test_user_id'] = 42;

        $result = $this->context->getCurrentUserId();

        self::assertSame(42, $result);
    }

    #[Test]
    public function returnsNullWhenNoUserIsLoggedIn(): void
    {
        $GLOBALS['__wp_test_user_id'] = 0;

        $result = $this->context->getCurrentUserId();

        self::assertNull($result);
    }

    #[Test]
    public function returnsNullWhenGlobalNotSet(): void
    {
        unset($GLOBALS['__wp_test_user_id']);

        $result = $this->context->getCurrentUserId();

        self::assertNull($result);
    }

    #[Test]
    public function returnsIntTypeForPositiveUserId(): void
    {
        $GLOBALS['__wp_test_user_id'] = 1;

        $result = $this->context->getCurrentUserId();

        self::assertIsInt($result);
    }

    #[Test]
    public function returnsCorrectValueForVariousUserIds(): void
    {
        $GLOBALS['__wp_test_user_id'] = 1;
        self::assertSame(1, $this->context->getCurrentUserId());

        $GLOBALS['__wp_test_user_id'] = 999;
        self::assertSame(999, $this->context->getCurrentUserId());

        $GLOBALS['__wp_test_user_id'] = 100000;
        self::assertSame(100000, $this->context->getCurrentUserId());
    }

    // -------------------------------------------------------------------------
    // Interface compliance
    // -------------------------------------------------------------------------

    #[Test]
    public function implementsUserContextResolverInterface(): void
    {
        $reflection = new ReflectionClass(WpUserContext::class);
        $interfaces = $reflection->getInterfaceNames();

        self::assertContains(
            UserContextResolverInterface::class,
            $interfaces,
        );
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new ReflectionClass(WpUserContext::class);

        self::assertTrue($reflection->isFinal());
    }
}
