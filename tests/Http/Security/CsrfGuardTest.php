<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Security;

use Middag\WordPress\Http\Security\CsrfGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CsrfGuard::class)]
final class CsrfGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_nonces'] = [CsrfGuard::NONCE_ACTION => 'valid-nonce'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    #[DataProvider('methodProvider')]
    public function isMutatingClassifiesTheHttpMethod(string $method, bool $expected): void
    {
        self::assertSame($expected, CsrfGuard::isMutating($method));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function methodProvider(): iterable
    {
        yield 'GET is safe' => ['GET', false];

        yield 'HEAD is safe' => ['HEAD', false];

        yield 'OPTIONS is safe' => ['OPTIONS', false];

        yield 'POST is mutating' => ['POST', true];

        yield 'PUT is mutating' => ['PUT', true];

        yield 'PATCH is mutating' => ['PATCH', true];

        yield 'DELETE is mutating' => ['DELETE', true];

        yield 'lowercase post is mutating' => ['post', true];
    }

    #[Test]
    public function extractNoncePrefersTheHeaderOverTheBody(): void
    {
        $nonce = CsrfGuard::extractNonce(
            ['HTTP_X_WP_NONCE' => 'from-header'],
            ['_wpnonce' => 'from-body'],
        );

        self::assertSame('from-header', $nonce);
    }

    #[Test]
    public function extractNonceFallsBackToTheBodyFieldWhenTheHeaderIsAbsentOrEmpty(): void
    {
        self::assertSame('from-body', CsrfGuard::extractNonce([], ['_wpnonce' => 'from-body']));
        self::assertSame('from-body', CsrfGuard::extractNonce(['HTTP_X_WP_NONCE' => ''], ['_wpnonce' => 'from-body']));
    }

    #[Test]
    public function extractNonceReturnsNullWhenNeitherCarriesANonce(): void
    {
        self::assertNull(CsrfGuard::extractNonce([], []));
    }

    #[Test]
    public function safeMethodsPassWithoutANonce(): void
    {
        self::assertTrue(CsrfGuard::isValidRequest('GET', null));
    }

    #[Test]
    public function mutatingRequestPassesWithAValidNonce(): void
    {
        self::assertTrue(CsrfGuard::isValidRequest('POST', 'valid-nonce'));
    }

    #[Test]
    public function mutatingRequestIsRejectedWithAnInvalidNonce(): void
    {
        self::assertFalse(CsrfGuard::isValidRequest('POST', 'forged-nonce'));
    }

    #[Test]
    public function mutatingRequestIsRejectedWhenTheNonceIsMissing(): void
    {
        self::assertFalse(CsrfGuard::isValidRequest('DELETE', null));
    }

    #[Test]
    public function failurePayloadCarriesAStableErrorCode(): void
    {
        $payload = CsrfGuard::failurePayload();

        self::assertSame('csrf_check_failed', $payload['error']);
        self::assertArrayHasKey('message', $payload);
    }

    #[Test]
    public function rejectsWithHttp403NotLaravels419(): void
    {
        // The Inertia client treats 419 as session-expired and auto-reloads;
        // WordPress-native nonce failure must surface as a plain 403 instead.
        self::assertSame(403, CsrfGuard::STATUS_FORBIDDEN);
    }

    #[Test]
    public function guardsASingleSharedNonceAction(): void
    {
        self::assertSame('middag_inertia', CsrfGuard::NONCE_ACTION);
    }
}
