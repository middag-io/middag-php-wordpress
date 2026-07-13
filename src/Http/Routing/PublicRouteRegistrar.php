<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Routing;

use Closure;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Support\HookSupport;
use Middag\WordPress\Support\RewriteSupport;
use Middag\WordPress\Support\SanitizeSupport;

/**
 * Public (front-end) routing surface: maps pretty URLs to handlers via the
 * WordPress rewrite system, the third routing surface beside the REST
 * {@see RestRouteRegistrar} and the admin {@see AdminRouteRegistrar}.
 *
 * All routes for one component funnel through a single per-component query var
 * (`{component}_route`, derived from componentName() — no brand literal), so two
 * plugins never collide on a shared `query_vars` slot. On register() it adds one
 * rewrite rule per route, registers the query var, and hooks `template_redirect`
 * to dispatch. Rewrite flushing is expensive and is driven from the plugin
 * lifecycle, not from register():
 *
 *     // composition root
 *     $lifecycle->onActivate(function () use ($public): void {
 *         $public->register();   // add the rules this request…
 *         $public->flushRules(); // …then persist them
 *     });
 *     $lifecycle->onDeactivate(fn () => $public->flushRules());
 *
 * @api
 */
final class PublicRouteRegistrar
{
    /**
     * @var array<string, array{regex: string, handler: Closure}>
     */
    private array $routes = [];

    private readonly string $queryVar;

    public function __construct(
        HostComponentContextInterface $context,
    ) {
        // sanitize_key keeps [a-z0-9_-]; collapse '-' to '_' so the query var is
        // a single valid identifier, then suffix '_route' to namespace it.
        $slug = str_replace('-', '_', SanitizeSupport::key($context->componentName()));
        $this->queryVar = $slug . '_route';
    }

    /**
     * Register a public route. $regex is a WordPress rewrite regex (matched
     * against the request path); $handler runs on `template_redirect` when the
     * route matches and owns its own output/exit.
     */
    public function addRoute(string $name, string $regex, callable $handler): void
    {
        $this->routes[$name] = [
            'regex' => $regex,
            'handler' => $handler(...),
        ];
    }

    /**
     * The per-component query var name that carries the matched route name.
     */
    public function queryVar(): string
    {
        return $this->queryVar;
    }

    /**
     * Wire the front-end routing on a normal request (call from `init`): expose
     * the query var, add a rewrite rule per route, and hook the dispatcher.
     */
    public function register(): void
    {
        HookSupport::addFilter('query_vars', [$this, 'registerQueryVar']);

        foreach ($this->routes as $name => $route) {
            RewriteSupport::addRule($route['regex'], 'index.php?' . $this->queryVar . '=' . $name, 'top');
        }

        HookSupport::addAction('template_redirect', [$this, 'dispatch']);
    }

    /**
     * `query_vars` filter callback: make the component's route var public.
     *
     * @param list<string> $vars
     *
     * @return list<string>
     */
    public function registerQueryVar(array $vars): array
    {
        $vars[] = $this->queryVar;

        return $vars;
    }

    /**
     * `template_redirect` action callback: invoke the matched route handler, or
     * do nothing (letting WordPress render the normal template) when no route
     * matched this request.
     */
    public function dispatch(): void
    {
        $name = (string) RewriteSupport::queryVar($this->queryVar);

        if ($name === '' || !isset($this->routes[$name])) {
            return;
        }

        ($this->routes[$name]['handler'])();
    }

    /**
     * Persist the current rewrite rule set. Expensive — drive it from the plugin
     * activation/deactivation lifecycle only (see the class docblock), never on a
     * normal request.
     */
    public function flushRules(): void
    {
        RewriteSupport::flush(true);
    }

    /**
     * @return array<string, string> route name => rewrite regex
     */
    public function getRegisteredRoutes(): array
    {
        return array_map(static fn (array $route): string => $route['regex'], $this->routes);
    }
}
