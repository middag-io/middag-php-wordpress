<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Inertia;

use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the non-terminating render path and request-detection/prop-resolution
 * helpers. The `sendJson()` and `location()` branches terminate with `exit`
 * and are intentionally left to the untested exit path (see the class docblock);
 * they are tracked in BACKLOG.md.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterCoverageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        HostContext::reset();
        InertiaAdapter::reset();
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'nonce-xyz'];
        unset($_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        HostContext::reset();
        InertiaAdapter::reset();
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function renderEmitsTheMountNodeForANonInertiaRequest(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';

        ob_start();
        InertiaAdapter::render('Dashboard', ['greeting' => 'hello']);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('id="middag-app"', $html);
        self::assertStringContainsString('data-page=', $html);
    }

    #[Test]
    public function renderResolvesClosurePropsAndNormalizesContract(): void
    {
        InertiaAdapter::share('shared', 'S');

        ob_start();
        InertiaAdapter::render('Page', [
            'lazy' => static fn (): string => 'lazy-value',
            'contract' => ['shell' => 'admin'],
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('lazy-value', $html);
        // The 'admin' shell is normalized to 'product' by PageContractNormalizer.
        self::assertStringContainsString('product', $html);
    }

    #[Test]
    public function isInertiaRequestDetectsTheHeaderFlag(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        self::assertTrue(InertiaAdapter::isInertiaRequest());

        $_SERVER['HTTP_X_INERTIA'] = 'false';
        self::assertFalse(InertiaAdapter::isInertiaRequest());

        unset($_SERVER['HTTP_X_INERTIA']);
        self::assertFalse(InertiaAdapter::isInertiaRequest());
    }
}
