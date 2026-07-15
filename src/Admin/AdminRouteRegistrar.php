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
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\Contract\RouterInterface;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Support\AdminSupport;
use Middag\WordPress\Support\SanitizeSupport;
use Middag\WordPress\Support\UserSupport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * Admin routing surface: registers a wp-admin menu tree whose pages all render
 * through one dispatch pipeline — the current page/route resolves against the
 * adapter {@see RouterInterface} route collection (controller-declared
 * `#[Route]` attributes or imperative registrations) and executes through the
 * framework HTTP kernel, inheriting its middleware pipeline and the
 * `#[Auth]`/`#[Middleware]`/`#[Nonce]` attributes.
 *
 * Generalizes the pattern each MIDDAG plugin used to hand-roll: the menu slug
 * derives from the host component name (no brand literal), unmatched routes
 * flow through the injected fallback (so the lib forces neither Inertia nor a
 * "Dashboard" page), and responses are emitted through the injected
 * {@see ResponseEmitterInterface} so the previously untestable header/echo
 * paths stay assertable. All wp-admin calls go through {@see AdminSupport}.
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
        private readonly RouterInterface $router,
        private readonly HttpKernelInterface $kernel,
        private readonly ResponseEmitterInterface $emitter,
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
     * the request at this boundary, then dispatches. The response body is
     * written inside the admin shell (no termination).
     */
    public function renderApp(): void
    {
        $this->respondToCurrentRequest(terminate: false);
    }

    /**
     * Early Inertia XHR interceptor (wire on `admin_init`): if this is an Inertia
     * request for one of this registrar's pages, render and terminate before
     * WordPress emits the admin HTML shell.
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

        $this->respondToCurrentRequest(terminate: true);
    }

    /**
     * Resolve a page/route/method to a route and execute it through the HTTP
     * kernel, falling back to the injected callable when nothing matches.
     * Pure — no superglobals.
     */
    public function dispatch(string $page, string $route, string $method): void
    {
        $path = $route !== '' ? '/' . trim($route, '/') : ($this->routeBases[$page] ?? '/');

        if (!$this->matches($path, $method)) {
            ($this->fallback)($page, $path);

            return;
        }

        $this->emit($this->kernel->handle($this->psrRequest($method, $path)));
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
     * Shared body of renderApp()/handleInertiaRequest(): capability gate at the
     * WordPress boundary (renderApp() is also reachable through admin_init and
     * admin-ajax.php, where WP's own menu gate never runs — fail-closed), then
     * dispatch; optionally terminate (Inertia XHR path, which must pre-empt the
     * admin shell).
     */
    private function respondToCurrentRequest(bool $terminate): void
    {
        $page = $this->requestString($_GET, 'page', $this->menuSlug());

        if (!$this->userCanAccess($page)) {
            return;
        }

        $route = $this->requestString($_GET, 'route');
        $method = $this->requestString($_SERVER, 'REQUEST_METHOD', 'GET');

        $this->dispatch($page, $route, $method);

        if ($terminate) {
            $this->emitter->terminate();
        }
    }

    /**
     * Whether the route collection has a route for this path/method. Matching
     * runs here (and again inside the kernel) so the no-match case can flow to
     * the fallback instead of a kernel 404.
     */
    private function matches(string $path, string $method): bool
    {
        $context = clone ($this->router->getContext() ?? new RequestContext());
        $context->setMethod($method);

        try {
            (new UrlMatcher($this->router->getRoutes(), $context))->match($path);
        } catch (MethodNotAllowedException|ResourceNotFoundException) {
            return false;
        }

        return true;
    }

    /**
     * Build the PSR-7 request the kernel dispatches: the current request's
     * query/body/cookies/files/server, re-targeted at the synthetic admin
     * route path (the kernel matches paths, not `admin.php?page=...&route=...`).
     */
    private function psrRequest(string $method, string $path): ServerRequestInterface
    {
        $base = Request::createFromGlobals();

        $request = Request::create(
            $path,
            $method,
            strtoupper($method) === 'GET' ? $base->query->all() : $base->request->all(),
            $base->cookies->all(),
            $base->files->all(),
            $base->server->all(),
            $base->getContent(),
        );

        $psr17 = new Psr17Factory();

        return (new PsrHttpFactory($psr17, $psr17, $psr17, $psr17))->createRequest($request);
    }

    /**
     * Emit a PSR-7 response through the emitter seam (status, headers, body).
     */
    private function emit(ResponseInterface $response): void
    {
        $this->emitter->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->emitter->header($name, $value);
            }
        }

        $body = (string) $response->getBody();

        if ($body !== '') {
            $this->emitter->write($body);
        }
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
