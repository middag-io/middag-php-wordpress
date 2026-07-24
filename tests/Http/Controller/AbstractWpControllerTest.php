<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Controller;

use Middag\WordPress\Http\Controller\AbstractWpController;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Tests\Http\RecordingEmitter;
use Middag\WordPress\Tests\Http\TerminateSignal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use WpDieSignal;

/**
 * Page-controller base wiring (wordpress#39): render() delegates to the
 * container-bound InertiaAdapter, and the auth gate maps onto WordPress's own
 * login/capability functions (including the meta-capability object id).
 *
 * @internal
 */
#[CoversClass(AbstractWpController::class)]
final class AbstractWpControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $GLOBALS['__wp_test_caps'] = [];
        $GLOBALS['__wp_test_cap_calls'] = [];
        unset($GLOBALS['__wp_test_logged_in'], $GLOBALS['__wp_test_auth_redirected']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        unset(
            $GLOBALS['__wp_test_caps'],
            $GLOBALS['__wp_test_cap_calls'],
            $GLOBALS['__wp_test_logged_in'],
            $GLOBALS['__wp_test_auth_redirected'],
        );
    }

    #[Test]
    public function renderDelegatesToTheContainerBoundInertiaAdapter(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=middag';
        $emitter = new RecordingEmitter();
        $adapter = new InertiaAdapter(new WpComponentContext('middag', '5.0.0'), $emitter);

        $controller = $this->controller();
        $controller->setContainer($this->containerWith($adapter));

        try {
            $controller->show('Dashboard', ['greeting' => 'hi']);
            self::fail('render() must terminate on an Inertia request');
        } catch (TerminateSignal) {
            // expected — the production exit stand-in
        }

        $page = json_decode($emitter->body, true);
        self::assertSame('Dashboard', $page['component']);
        self::assertSame('hi', $page['props']['greeting']);
    }

    #[Test]
    public function renderThrowsWhenNoInertiaAdapterIsBound(): void
    {
        $controller = $this->controller();
        $controller->setContainer($this->emptyContainer());

        $this->expectException(RuntimeException::class);
        $controller->show('Dashboard');
    }

    #[Test]
    public function requireLoginRedirectsAnAnonymousVisitor(): void
    {
        $GLOBALS['__wp_test_logged_in'] = false;

        $this->controller()->setRequireLogin();

        self::assertTrue($GLOBALS['__wp_test_auth_redirected'] ?? false, 'auth_redirect() should fire');
    }

    #[Test]
    public function requireLoginPassesForALoggedInUser(): void
    {
        $GLOBALS['__wp_test_logged_in'] = true;

        $this->controller()->setRequireLogin();

        self::assertArrayNotHasKey('__wp_test_auth_redirected', $GLOBALS, 'no redirect for a logged-in user');
    }

    #[Test]
    public function capabilitiesPassWhenTheUserHoldsThemAll(): void
    {
        $GLOBALS['__wp_test_caps'] = ['manage_options' => true, 'edit_pages' => true];

        $this->controller()->setRequireCapabilities(['manage_options', 'edit_pages']);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function missingCapabilityHaltsWithA403(): void
    {
        $GLOBALS['__wp_test_caps'] = ['manage_options' => false];

        try {
            $this->controller()->setRequireCapabilities(['manage_options']);
            self::fail('a missing capability must halt');
        } catch (WpDieSignal $wpDieSignal) {
            self::assertSame(403, $wpDieSignal->wpArgs['response'] ?? null);
        }
    }

    #[Test]
    public function instanceIdIsForwardedAsTheMetaCapabilityObjectId(): void
    {
        $GLOBALS['__wp_test_caps'] = ['edit_post' => true];

        $this->controller()->setRequireCapabilities(['edit_post'], 'system', 123);

        $lastCall = end($GLOBALS['__wp_test_cap_calls']);
        self::assertSame('edit_post', $lastCall['capability']);
        self::assertSame([123], $lastCall['args'], 'the instance id is passed as the meta-cap object id');
    }

    #[Test]
    public function globalCapabilityCheckForwardsNoObjectId(): void
    {
        $GLOBALS['__wp_test_caps'] = ['manage_options' => true];

        $this->controller()->setRequireCapabilities(['manage_options']);

        $lastCall = end($GLOBALS['__wp_test_cap_calls']);
        self::assertSame([], $lastCall['args'], 'a global cap check passes no object id');
    }

    /**
     * A concrete controller exposing the protected render() for the test.
     */
    private function controller(): AbstractWpController
    {
        return new class extends AbstractWpController {
            /** @param array<string, mixed> $props */
            public function show(string $component, array $props = []): void
            {
                $this->render($component, $props);
            }
        };
    }

    private function containerWith(InertiaAdapter $adapter): ContainerInterface
    {
        return new class($adapter) implements ContainerInterface {
            public function __construct(private readonly InertiaAdapter $adapter) {}

            public function get(string $id): mixed
            {
                return $id === InertiaAdapter::class ? $this->adapter : null;
            }

            public function has(string $id): bool
            {
                return $id === InertiaAdapter::class;
            }
        };
    }

    private function emptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                return null;
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
