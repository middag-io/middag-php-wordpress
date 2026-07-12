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

use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers the non-terminating render path and request-detection/prop-resolution
 * helpers. The `sendJson()` and `location()` branches terminate with `exit`
 * and are intentionally left to the untested exit path (see the class docblock);
 * they are tracked in BACKLOG.md.
 *
 * The adapter is instance-scoped now: each test builds one from a
 * {@see WpComponentContext} (component `middag`, so the mount id resolves to
 * `middag-app`) instead of touching process-wide static state.
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterCoverageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    private InertiaAdapter $adapter;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'));
        $GLOBALS['__wp_test_nonces'] = ['middag_inertia' => 'nonce-xyz'];
        unset($_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        unset($GLOBALS['__wp_test_nonces']);
    }

    #[Test]
    public function renderEmitsTheMountNodeForANonInertiaRequest(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';

        ob_start();
        $this->adapter->render('Dashboard', ['greeting' => 'hello']);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('id="middag-app"', $html);
        self::assertStringContainsString('data-page=', $html);
    }

    #[Test]
    public function renderResolvesClosurePropsAndForwardsCanonicalContractVerbatim(): void
    {
        $this->adapter->share('shared', 'S');

        ob_start();
        $this->adapter->render('Page', [
            'lazy' => static fn (): string => 'lazy-value',
            'contract' => ['shell' => 'product', 'layout' => ['regions' => []]],
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('lazy-value', $html);
        // The canonical contract is emitted verbatim: no schema rewriting.
        self::assertStringContainsString('product', $html);
    }

    #[Test]
    public function renderDoesNotSilentlyNormalizeALegacyContract(): void
    {
        ob_start();
        $this->adapter->render('Page', [
            'contract' => [
                'shell' => 'admin',
                'layout' => [
                    'regions' => [
                        'main' => [
                            [
                                'type' => 'dense_table',
                                'data' => [
                                    'columns' => [
                                        ['key' => 'state', 'type' => 'badge', 'badgeVariants' => ['on' => 'success']],
                                    ],
                                    'pagination' => ['currentPage' => 1, 'pages' => 4],
                                    'emptyMessage' => 'Empty',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $html = (string) ob_get_clean();

        // The legacy contract is forwarded unchanged: the old auto-migration
        // (shell admin->product, badge->status/statusMap, currentPage->page,
        // emptyMessage->emptyState) no longer runs. The frontend rejects the
        // legacy shape instead of it being silently accepted here.
        self::assertStringContainsString('admin', $html);
        self::assertStringContainsString('badgeVariants', $html);
        self::assertStringContainsString('currentPage', $html);
        self::assertStringContainsString('emptyMessage', $html);
        self::assertStringNotContainsString('product', $html);
        self::assertStringNotContainsString('statusMap', $html);
        self::assertStringNotContainsString('emptyState', $html);
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

    /**
     * isPartialReload()/getPartialData() are pure instance helpers only called
     * from sendJson() — which terminates with `exit` and is intentionally left
     * untested (see the class docblock). Reflection drives them directly on the
     * adapter instance so their own logic is covered without the exit path.
     */
    #[Test]
    public function isPartialReloadMatchesOnlyTheRequestedComponent(): void
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'isPartialReload');

        $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'] = 'Dashboard';
        self::assertTrue($method->invoke($this->adapter, 'Dashboard'));
        self::assertFalse($method->invoke($this->adapter, 'Other'));

        unset($_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT']);
        self::assertFalse($method->invoke($this->adapter, 'Dashboard'));
    }

    #[Test]
    public function getPartialDataSplitsTheCommaSeparatedHeaderOrReturnsEmpty(): void
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'getPartialData');

        $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'] = 'user,stats';
        self::assertSame(['user', 'stats'], $method->invoke($this->adapter));

        unset($_SERVER['HTTP_X_INERTIA_PARTIAL_DATA']);
        self::assertSame([], $method->invoke($this->adapter));
    }
}
