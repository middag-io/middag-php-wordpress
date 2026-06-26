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

use Middag\WordPress\Support\SecuritySupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SecuritySupport::class)]
final class SecuritySupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'good-token'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function createNonceDelegatesToWordPress(): void
    {
        self::assertSame('good-token', SecuritySupport::createNonce('middag_inertia'));
    }

    #[Test]
    public function verifyNonceReturnsTrueForAMatchingToken(): void
    {
        self::assertTrue(SecuritySupport::verifyNonce('good-token', 'middag_inertia'));
    }

    #[Test]
    public function verifyNonceReturnsFalseForAMismatchedToken(): void
    {
        self::assertFalse(SecuritySupport::verifyNonce('wrong-token', 'middag_inertia'));
    }

    #[Test]
    public function verifyNonceReturnsFalseForANullOrEmptyNonceWithoutTouchingWordPress(): void
    {
        self::assertFalse(SecuritySupport::verifyNonce(null, 'middag_inertia'));
        self::assertFalse(SecuritySupport::verifyNonce('', 'middag_inertia'));
    }
}
