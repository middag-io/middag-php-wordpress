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

/**
 * Covers the non-terminating (first-visit) render path and the static request
 * detection helper. Partial-reload / prop-resolution semantics now live in the
 * framework wire the adapter delegates to and are exercised end-to-end through
 * {@see InertiaAdapterEmitterTest} and {@see InertiaAdapterWireTest}.
 *
 * The adapter is instance-scoped: each test builds one from a
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
        // Pure now: takes the server array instead of touching $_SERVER.
        self::assertTrue(InertiaAdapter::isInertiaRequest(['HTTP_X_INERTIA' => 'true']));
        self::assertFalse(InertiaAdapter::isInertiaRequest(['HTTP_X_INERTIA' => 'false']));
        self::assertFalse(InertiaAdapter::isInertiaRequest([]));
    }
}
