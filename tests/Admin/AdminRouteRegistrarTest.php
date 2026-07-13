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

use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Admin\MenuPage;
use Middag\WordPress\Admin\SubMenuPage;
use Middag\WordPress\Http\Routing\Router;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(AdminRouteRegistrar::class)]
final class AdminRouteRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_admin_menus'] = [];
        $GLOBALS['__wp_test_admin_submenus'] = [];
        $GLOBALS['__wp_test_caps'] = [];
        AdminRouteTestController::$calls = [];
        AdminRouteTestController::$constructions = 0;
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
        $registrar = $this->makeRegistrar(new Router(), new AdminRouteTestContainer(), null, 'acme');

        self::assertSame('acme', $registrar->menuSlug());
        self::assertSame('acme-things', $registrar->submenuSlug('things'));
    }

    #[Test]
    public function registerBuildsTheMenuTreeThroughAdminSupport(): void
    {
        $registrar = $this->makeRegistrar(new Router(), new AdminRouteTestContainer());
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
        $registrar = $this->makeRegistrar(new Router(), new AdminRouteTestContainer());
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'edit_posts'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        self::assertSame('edit_posts', $GLOBALS['__wp_test_admin_submenus']['acme-things']['capability']);
    }

    #[Test]
    public function dispatchResolvesTheControllerFromTheContainer(): void
    {
        $controller = new AdminRouteTestController();
        AdminRouteTestController::$constructions = 0;

        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => $controller]),
        );

        $registrar->dispatch('acme', '/things', 'GET');

        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
        self::assertSame(0, AdminRouteTestController::$constructions);
    }

    #[Test]
    public function dispatchPassesRouteParamsToTheControllerMethod(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );

        $registrar->dispatch('acme', '/things/42', 'GET');

        self::assertSame([['method' => 'show', 'args' => ['42']]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchInstantiatesTheControllerWhenItIsNotInTheContainer(): void
    {
        $registrar = $this->makeRegistrar($this->routerWithThings(), new AdminRouteTestContainer());

        $registrar->dispatch('acme', '/things', 'GET');

        self::assertSame(1, AdminRouteTestController::$constructions);
        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchInvokesTheFallbackWhenNoRouteMatches(): void
    {
        $fallbackCalls = [];
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer(),
            static function (string $page, string $path) use (&$fallbackCalls): void {
                $fallbackCalls[] = [$page, $path];
            },
        );

        $registrar->dispatch('acme', '/does-not-exist', 'GET');

        self::assertSame([['acme', '/does-not-exist']], $fallbackCalls);
        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchUsesTheRegisteredRouteBaseWhenNoExplicitRouteIsGiven(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(
            new MenuPage('Acme', 'Acme'),
            [new SubMenuPage('things', 'Things', 'Things', '/things')],
        );

        // No explicit ?route → derived from the submenu's route base.
        $registrar->dispatch('acme-things', '', 'GET');

        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function renderAppReadsTheRequestAndDispatches(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );

        $this->grantCapability();
        $_GET['page'] = 'acme';
        $_GET['route'] = '/things/7';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $registrar->renderApp();

        self::assertSame([['method' => 'show', 'args' => ['7']]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function handleInertiaRequestIgnoresNonInertiaRequests(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(new MenuPage('Acme', 'Acme'), [new SubMenuPage('things', 'Things', 'Things', '/things')]);

        $_GET['page'] = 'acme-things';
        // No HTTP_X_INERTIA header.
        $registrar->handleInertiaRequest();

        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function handleInertiaRequestDispatchesForAnOwnedInertiaPage(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(new MenuPage('Acme', 'Acme'), [new SubMenuPage('things', 'Things', 'Things', '/things')]);

        $this->grantCapability();
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_GET['page'] = 'acme-things';
        $registrar->handleInertiaRequest();

        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function handleInertiaRequestIgnoresAnInertiaRequestForAnUnownedPage(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(new MenuPage('Acme', 'Acme'), [new SubMenuPage('things', 'Things', 'Things', '/things')]);

        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_GET['page'] = 'someone-else';
        $registrar->handleInertiaRequest();

        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function renderAppDeniesDispatchWhenTheUserLacksThePageCapability(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );

        // No capability granted → current_user_can() returns false.
        $_GET['page'] = 'acme';
        $_GET['route'] = '/things';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $registrar->renderApp();

        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function handleInertiaRequestDeniesWhenTheUserLacksThePageCapability(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(new MenuPage('Acme', 'Acme'), [new SubMenuPage('things', 'Things', 'Things', '/things')]);

        // Inertia request for an owned page, but no capability granted.
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $_GET['page'] = 'acme-things';
        $registrar->handleInertiaRequest();

        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchUsesTheMainMenuRouteBaseForTheTopLevelSlug(): void
    {
        $router = new Router();
        $router->get('/overview', [AdminRouteTestController::class, 'index']);

        $registrar = $this->makeRegistrar(
            $router,
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );
        $registrar->register(
            new MenuPage('Acme', 'Acme', 'manage_options', '', null, '/overview'),
            [],
        );

        // Empty route on the main slug → derived from MenuPage::routeBase.
        $registrar->dispatch('acme', '', 'GET');

        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchIgnoresAResolvedRouteWhoseControllerLacksTheMethod(): void
    {
        $router = new Router();
        $router->get('/gone', [AdminRouteTestController::class, 'doesNotExist']);

        $registrar = $this->makeRegistrar(
            $router,
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );

        // Route matches, but the controller has no such method → silent no-op, no crash.
        $registrar->dispatch('acme', '/gone', 'GET');

        self::assertSame([], AdminRouteTestController::$calls);
    }

    #[Test]
    public function dispatchFallsBackToRootWhenThePageHasNoRegisteredRouteBase(): void
    {
        $fallbackCalls = [];
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer(),
            static function (string $page, string $path) use (&$fallbackCalls): void {
                $fallbackCalls[] = [$page, $path];
            },
        );

        // Empty route + a page never registered → path defaults to '/', which
        // resolves to nothing, so the fallback fires with the '/' path.
        $registrar->dispatch('ghost-page', '', 'GET');

        self::assertSame([['ghost-page', '/']], $fallbackCalls);
    }

    #[Test]
    public function renderAppTreatsAnArrayValuedPageParamAsAbsent(): void
    {
        $registrar = $this->makeRegistrar(
            $this->routerWithThings(),
            new AdminRouteTestContainer([AdminRouteTestController::class => new AdminRouteTestController()]),
        );

        $this->grantCapability();
        // A crafted array-valued param must not crash requestString(); it falls
        // back to the default (the menu slug) instead of a TypeError.
        $_GET['page'] = ['injected'];
        $_GET['route'] = '/things';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $registrar->renderApp();

        self::assertSame([['method' => 'index', 'args' => []]], AdminRouteTestController::$calls);
    }

    private function grantCapability(string $capability = 'manage_options'): void
    {
        $GLOBALS['__wp_test_caps'][$capability] = true;
    }

    /**
     * @param null|callable(string, string): void $fallback
     */
    private function makeRegistrar(
        Router $router,
        ContainerInterface $container,
        ?callable $fallback = null,
        string $component = 'acme',
    ): AdminRouteRegistrar {
        return new AdminRouteRegistrar(
            new WpComponentContext($component, '5.0.0'),
            $router,
            $container,
            $fallback ?? static fn (string $page, string $path): null => null,
        );
    }

    private function routerWithThings(): Router
    {
        $router = new Router();
        $router->get('/things', [AdminRouteTestController::class, 'index']);
        $router->get('/things/{id}', [AdminRouteTestController::class, 'show']);

        return $router;
    }
}

/**
 * In-memory PSR-11 container double.
 *
 * @internal
 */
final class AdminRouteTestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(private array $services = []) {}

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

/**
 * Controller double recording method invocations and constructions.
 *
 * @internal
 */
final class AdminRouteTestController
{
    /** @var list<array{method: string, args: array<int, ?string>}> */
    public static array $calls = [];

    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    public function index(): void
    {
        self::$calls[] = ['method' => 'index', 'args' => []];
    }

    public function show(?string $id = null): void
    {
        self::$calls[] = ['method' => 'show', 'args' => [$id]];
    }
}
