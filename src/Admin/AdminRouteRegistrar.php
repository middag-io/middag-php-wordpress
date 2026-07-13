<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Admin;

use Closure;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\ControllerResolver;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Http\Routing\Router;
use Middag\WordPress\Support\AdminSupport;
use Middag\WordPress\Support\SanitizeSupport;
use Middag\WordPress\Support\UserSupport;
use Psr\Container\ContainerInterface;

/**
 * Admin routing surface: registers a wp-admin menu tree whose pages all render
 * through one dispatch callback, resolving the current page/route to a
 * controller via the adapter {@see Router} and a PSR-11 container.
 *
 * Generalizes the pattern each MIDDAG plugin used to hand-roll: the menu slug is
 * derived from the host component name (no brand literal), page rendering flows
 * through the injected fallback for unmatched routes (so the lib forces neither
 * Inertia nor a "Dashboard" page), and controllers are resolved from the
 * plugin's own container so they inherit its injected identity. All wp-admin
 * calls go through {@see AdminSupport} so the registrar is unit-testable off a
 * WordPress runtime.
 *
 * @api
 */
final class AdminRouteRegistrar
{
    /**
     * @var list<string>
     */
    private array $pageHookSuffixes = [];

    /**
     * @var list<string>
     */
    private array $pageSlugs = [];

    /**
     * @var array<string, string> page slug => route base
     */
    private array $routeBases = [];

    /**
     * @var array<string, string> page slug => required capability
     */
    private array $capabilities = [];

    /**
     * Capability enforced for a page not present in {@see self::$capabilities}
     * (defense-in-depth default; overwritten by the main menu's capability on
     * register()). Fail-closed to WordPress's admin default.
     */
    private string $defaultCapability = 'manage_options';

    private readonly Closure $fallback;

    /**
     * @param callable(string, string): void $fallback runs when no route matches;
     *                                                 receives (page slug, resolved path)
     */
    public function __construct(
        private readonly HostComponentContextInterface $context,
        private readonly Router $router,
        private readonly ContainerInterface $container,
        callable $fallback,
    ) {
        $this->fallback = Closure::fromCallable($fallback);
    }

    /**
     * Top-level menu slug: the host component name.
     */
    public function menuSlug(): string
    {
        return $this->context->componentName();
    }

    /**
     * Submenu slug: `{component}-{suffix}`.
     */
    public function submenuSlug(string $slugSuffix): string
    {
        return $this->context->componentName() . '-' . $slugSuffix;
    }

    /**
     * Register the wp-admin menu tree. Every page renders through renderApp().
     *
     * @param list<SubMenuPage> $subPages
     */
    public function register(MenuPage $main, array $subPages): void
    {
        $mainSlug = $this->menuSlug();
        $this->pageSlugs[] = $mainSlug;
        $this->routeBases[$mainSlug] = $main->routeBase;
        $this->capabilities[$mainSlug] = $main->capability;
        $this->defaultCapability = $main->capability;

        $this->pageHookSuffixes[] = AdminSupport::addMenuPage(
            $main->pageTitle,
            $main->menuTitle,
            $main->capability,
            $mainSlug,
            [$this, 'renderApp'],
            $main->icon,
            $main->position,
        );

        foreach ($subPages as $sub) {
            $subSlug = $this->submenuSlug($sub->slugSuffix);
            $this->pageSlugs[] = $subSlug;
            $this->routeBases[$subSlug] = $sub->routeBase;
            $this->capabilities[$subSlug] = $sub->capability ?? $main->capability;

            $suffix = AdminSupport::addSubmenuPage(
                $mainSlug,
                $sub->pageTitle,
                $sub->menuTitle,
                $sub->capability ?? $main->capability,
                $subSlug,
                [$this, 'renderApp'],
            );

            if ($suffix !== false) {
                $this->pageHookSuffixes[] = $suffix;
            }
        }
    }

    /**
     * wp-admin page render callback: reads the current page/route/method from
     * the request at this boundary, then dispatches. Superglobals are read only
     * here; {@see self::dispatch()} is pure.
     */
    public function renderApp(): void
    {
        $page = $this->requestString($_GET, 'page', $this->menuSlug());

        // Capability gate at the WordPress boundary. WP enforces the page
        // capability on the normal menu-render path, but renderApp() is also
        // reachable through handleInertiaRequest() on admin_init (and via
        // admin-ajax.php / admin-post.php), where that menu gate never runs.
        // Fail-closed here so no admin controller is invoked for a user lacking
        // the page's capability.
        if (!$this->userCanAccess($page)) {
            return;
        }

        $route = $this->requestString($_GET, 'route');
        $method = $this->requestString($_SERVER, 'REQUEST_METHOD', 'GET');

        $this->dispatch($page, $route, $method);
    }

    /**
     * Resolve a page/route/method to a controller and invoke it, falling back to
     * the injected callable when nothing matches. Pure — no superglobals.
     */
    public function dispatch(string $page, string $route, string $method): void
    {
        $path = $route !== '' ? '/' . trim($route, '/') : ($this->routeBases[$page] ?? '/');

        $match = $this->router->resolve($method, $path);

        if ($match === null) {
            ($this->fallback)($page, $path);

            return;
        }

        $controller = ControllerResolver::resolve($this->container, $match['controller']);

        if (method_exists($controller, $match['method'])) {
            $controller->{$match['method']}(...array_values($match['params']));
        }
    }

    /**
     * Early Inertia XHR interceptor (wire on `admin_init`): if this is an Inertia
     * request for one of this registrar's pages, render before WordPress emits
     * the admin HTML shell.
     */
    public function handleInertiaRequest(): void
    {
        if (!InertiaAdapter::isInertiaRequest($_SERVER)) {
            return;
        }

        $page = $this->requestString($_GET, 'page');
        if (!in_array($page, $this->pageSlugs, true)) {
            return;
        }

        $this->renderApp();
    }

    /**
     * Registered page hook suffixes (for the consumer's asset-enqueue guard).
     *
     * @return list<string>
     */
    public function pageHookSuffixes(): array
    {
        return $this->pageHookSuffixes;
    }

    /**
     * Whether the current user may access $page, checked against the page's
     * registered capability (or the default when the page is unknown). Reads
     * through {@see UserSupport::currentUserCan()}, which fail-closes to false
     * when WordPress is absent.
     */
    private function userCanAccess(string $page): bool
    {
        return UserSupport::currentUserCan($this->capabilities[$page] ?? $this->defaultCapability);
    }

    /**
     * Read a sanitized string from a request superglobal, or $default when the
     * key is absent or not a string (defends against array-valued params).
     *
     * @param array<string, mixed> $source
     */
    private function requestString(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? SanitizeSupport::text($value) : $default;
    }
}
