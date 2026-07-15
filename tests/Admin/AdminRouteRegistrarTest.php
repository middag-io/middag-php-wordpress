<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Admin;

use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Admin\MenuPage;
use Middag\WordPress\Admin\SubMenuPage;
use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\Routing\WpRouter;
use Middag\WordPress\Runtime\WpComponentContext;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
#[CoversClass(AdminRouteRegistrar::class)]
final class AdminRouteRegistrarTest extends TestCase
{
    private WpRouter $router;

    private FakeHttpKernel $kernel;

    private RecordingEmitter $emitter;

    /** @var list<array{0: string, 1: string}> */
    private array $fallbackCalls = [];

    protected function setUp(): void
    {
        $GLOBALS['__wp_test_admin_menus'] = [];
        $GLOBALS['__wp_test_admin_submenus'] = [];
        $GLOBALS['__wp_test_caps'] = [];

        $this->router = new WpRouter();
        $this->kernel = new FakeHttpKernel();
        $this->emitter = new RecordingEmitter();
        $this->fallbackCalls = [];

        unset($_GET['page'], $_GET['route'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_INERTIA']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_admin_menus'],
            $GLOBALS['__wp_test_admin_submenus'],
            $GLOBALS['__wp_test_caps'],
            $_GET['page'],
            $_GET['route'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['HTTP_X_INERTIA'],
        );
    }

    #[Test]
    public function slugsDeriveFromComponentName(): void
    {
        $registrar = $this->makeRegistrar();

        self::assertSame('acme', $registrar->menuSlug());
        self::assertSame('acme-things', $registrar->submenuSlug('things'));
    }

    #[Test]
    public function registerBuildsTheMenuTreeThroughAdminSupport(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options', 'dashicons-star', 3),
            [
                new SubMenuPage('things', 'Things', 'Things', '/things'),
                new SubMenuPage('reports', 'Reports', 'Reports', '/reports'),
            ],
        );

        self::assertArrayHasKey('acme', $GLOBALS['__wp_test_admin_menus']);
        self::assertArrayHasKey('acme-things', $GLOBALS['__wp_test_admin_submenus']);
        self::assertArrayHasKey('acme-reports', $GLOBALS['__wp_test_admin_submenus']);
        self::assertSame('acme', $GLOBALS['__wp_test_admin_submenus']['acme-things']['parent_slug']);

        // Main + 2 submenus → 3 tracked hook suffixes.
        self::assertCount(3, $registrar->pageHookSuffixes());
    }

    #[Test]
    public function submenuInheritsParentCapabilityWhenNoneGiven(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'edit_posts'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        self::assertSame('edit_posts', $GLOBALS['__wp_test_admin_submenus']['acme-things']['capability']);
    }

    #[Test]
    public function dispatchExecutesAMatchedRouteThroughTheKernel(): void
    {
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();

        $registrar->dispatch('acme-things', '/things', 'GET');

        self::assertNotNull($this->kernel->handled);
        self::assertSame('/things', $this->kernel->handled->getUri()->getPath());
        self::assertSame('GET', $this->kernel->handled->getMethod());
        self::assertSame([], $this->fallbackCalls);
    }

    #[Test]
    public function dispatchEmitsTheKernelResponse(): void
    {
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $this->kernel->respondWith = new Response(201, ['X-Custom' => 'yes'], 'created');
        $registrar = $this->makeRegistrar();

        $registrar->dispatch('acme-things', '/things', 'GET');

        self::assertSame(201, $this->emitter->statuses[0]);
        self::assertContains(['X-Custom', 'yes'], $this->emitter->headers);
        self::assertSame('created', implode('', $this->emitter->written));
    }

    #[Test]
    public function dispatchInvokesTheFallbackWhenNoRouteMatches(): void
    {
        $registrar = $this->makeRegistrar();

        $registrar->dispatch('acme-things', '/nowhere', 'GET');

        self::assertNull($this->kernel->handled);
        self::assertSame([['acme-things', '/nowhere']], $this->fallbackCalls);
    }

    #[Test]
    public function dispatchFallsBackToRootWhenThePageHasNoRegisteredRouteBase(): void
    {
        $registrar = $this->makeRegistrar();

        // Empty route + a page never registered → path defaults to '/', which
        // resolves to nothing, so the fallback fires with the '/' path.
        $registrar->dispatch('ghost-page', '', 'GET');

        self::assertSame([['ghost-page', '/']], $this->fallbackCalls);
    }

    #[Test]
    public function dispatchRespectsTheHttpMethodWhenMatching(): void
    {
        $this->router->getRoutes()->add('things_create', new Route(
            '/things',
            ['_controller' => [FixtureAdminController::class, 'create']],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $registrar = $this->makeRegistrar();

        $registrar->dispatch('acme-things', '/things', 'GET');

        // GET must not match the POST-only route: fallback runs.
        self::assertNull($this->kernel->handled);
        self::assertCount(1, $this->fallbackCalls);

        $registrar->dispatch('acme-things', '/things', 'POST');

        self::assertNotNull($this->kernel->handled);
        self::assertSame('POST', $this->kernel->handled->getMethod());
    }

    #[Test]
    public function dispatchUsesTheRegisteredRouteBaseWhenNoExplicitRouteIsGiven(): void
    {
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        $registrar->dispatch('acme-things', '', 'GET');

        self::assertNotNull($this->kernel->handled);
        self::assertSame('/things', $this->kernel->handled->getUri()->getPath());
    }

    #[Test]
    public function renderAppReadsTheRequestAndDispatches(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        $_GET['page'] = 'acme-things';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $registrar->renderApp();

        self::assertNotNull($this->kernel->handled);
        self::assertSame('/things', $this->kernel->handled->getUri()->getPath());
        self::assertFalse($this->emitter->terminated);
    }

    #[Test]
    public function renderAppTreatsAnArrayValuedPageParamAsAbsent(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();

        // A crafted array-valued param must not crash requestString(); it falls
        // back to the default (the menu slug) instead of a TypeError.
        $_GET['page'] = ['injected'];
        $_GET['route'] = '/things';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $registrar->renderApp();

        self::assertNotNull($this->kernel->handled);
        self::assertSame('/things', $this->kernel->handled->getUri()->getPath());
    }

    #[Test]
    public function renderAppDeniesDispatchWhenTheUserLacksThePageCapability(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = false;
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        $_GET['page'] = 'acme-things';

        $registrar->renderApp();

        self::assertNull($this->kernel->handled);
        self::assertSame([], $this->fallbackCalls);
    }

    #[Test]
    public function handleInertiaRequestIgnoresNonInertiaRequests(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register(new MenuPage('Acme', 'Acme', 'manage_options'), []);

        $_GET['page'] = 'acme';

        $registrar->handleInertiaRequest();

        self::assertNull($this->kernel->handled);
        self::assertFalse($this->emitter->terminated);
    }

    #[Test]
    public function handleInertiaRequestDispatchesAndTerminatesForAnOwnedPage(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;
        $this->router->register('things_index', '/things', FixtureAdminController::class, 'index');
        $registrar = $this->makeRegistrar();
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        $_GET['page'] = 'acme-things';
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            $registrar->handleInertiaRequest();
            self::fail('Expected the recording emitter to signal termination.');
        } catch (EmitterTerminated) {
            // expected: the Inertia XHR path pre-empts the admin shell.
        }

        self::assertNotNull($this->kernel->handled);
        self::assertTrue($this->emitter->terminated);
    }

    #[Test]
    public function handleInertiaRequestIgnoresAnInertiaRequestForAnUnownedPage(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register(new MenuPage('Acme', 'Acme', 'manage_options'), []);

        $_GET['page'] = 'somebody-else';
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $registrar->handleInertiaRequest();

        self::assertNull($this->kernel->handled);
        self::assertFalse($this->emitter->terminated);
    }

    #[Test]
    public function handleInertiaRequestDeniesWhenTheUserLacksThePageCapability(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = false;
        $registrar = $this->makeRegistrar();
        $registrar->register(new MenuPage('Acme', 'Acme', 'manage_options'), []);

        $_GET['page'] = 'acme';
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $registrar->handleInertiaRequest();

        self::assertNull($this->kernel->handled);
        self::assertFalse($this->emitter->terminated);
    }

    private function makeRegistrar(string $component = 'acme'): AdminRouteRegistrar
    {
        return new AdminRouteRegistrar(
            new WpComponentContext($component, '1.0.0'),
            $this->router,
            $this->kernel,
            $this->emitter,
            function (string $page, string $path): void {
                $this->fallbackCalls[] = [$page, $path];
            },
        );
    }
}

final class FixtureAdminController
{
    public function index(): void {}

    public function create(): void {}
}

final class FakeHttpKernel implements HttpKernelInterface
{
    public ?ServerRequestInterface $handled = null;

    public ?ResponseInterface $respondWith = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handled = $request;

        return $this->respondWith ?? new Response(200, [], 'ok');
    }
}

final class EmitterTerminated extends RuntimeException {}

final class RecordingEmitter implements ResponseEmitterInterface
{
    /** @var list<int> */
    public array $statuses = [];

    /** @var list<array{0: string, 1: string}> */
    public array $headers = [];

    /** @var list<string> */
    public array $written = [];

    public bool $terminated = false;

    public function status(int $code): void
    {
        $this->statuses[] = $code;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[] = [$name, $value];
    }

    public function redirect(string $url): void {}

    public function write(string $body): void
    {
        $this->written[] = $body;
    }

    public function terminate(): never
    {
        $this->terminated = true;

        throw new EmitterTerminated('terminated');
    }
}
