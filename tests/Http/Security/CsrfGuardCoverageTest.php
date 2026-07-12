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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the pass-through paths of enforce() that read the superglobals and
 * return without rejecting. The reject() branch terminates with `exit` and is
 * the intentionally-untested exit path (see the class docblock); it is tracked
 * in BACKLOG.md. The host passes the component's nonce action.
 *
 * @internal
 */
#[CoversClass(CsrfGuard::class)]
final class CsrfGuardCoverageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    private string $nonceAction;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->nonceAction = CsrfGuard::nonceAction('middag');
        $GLOBALS['__wp_test_nonces'] = [$this->nonceAction => 'valid-nonce'];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function enforcePassesSafeVerbsWithoutANonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        unset($_SERVER['HTTP_X_WP_NONCE']);

        CsrfGuard::enforce($this->nonceAction);

        // Reaching this point means enforce() returned instead of exiting.
        self::assertTrue(true);
    }

    #[Test]
    public function enforcePassesMutatingRequestsCarryingAValidHeaderNonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_WP_NONCE'] = 'valid-nonce';
        $_POST = [];

        CsrfGuard::enforce($this->nonceAction);

        self::assertTrue(true);
    }

    #[Test]
    public function enforcePassesMutatingRequestsCarryingAValidBodyNonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        unset($_SERVER['HTTP_X_WP_NONCE']);
        $_POST = ['_wpnonce' => 'valid-nonce'];

        CsrfGuard::enforce($this->nonceAction);

        self::assertTrue(true);
    }
}
