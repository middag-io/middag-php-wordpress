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

/**
 * Lightweight HTTP-method-aware router for the WordPress admin frontend.
 *
 * The framework's symfony-based router (`RouterInterface`) handles
 * registration + URL generation server-side. This adapter-local router
 * complements it with a simple `method+path → controller` resolver for
 * consumers rendering inside WordPress's `add_menu_page` callbacks.
 * Route patterns use the `{param}` placeholder syntax and are compiled
 * to PCRE on registration.
 *
 * @deprecated since 1.7 — use {@see WpRouter} (Symfony RouteCollection +
 *             framework `#[Route]` attribute discovery) with `WpHttpKernel`;
 *             the central route array this class implies is superseded by
 *             controller-declared attributes. Scheduled for removal in the
 *             next major.
 *
 * @api
 */
final class Router
{
    /**
     * @var array<string, list<array{pattern: string, handler: array{0: class-string, 1: string}, paramNames: list<string>}>>
     */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $handler [controller FQCN, method name]
     */
    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function patch(string $path, array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Resolve a request to its controller binding.
     *
     * @return null|array{controller: class-string, method: string, params: array<string, ?string>}
     */
    public function resolve(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($route['paramNames'] as $name) {
                    $params[$name] = $matches[$name] ?? null;
                }

                return [
                    'controller' => $route['handler'][0],
                    'method' => $route['handler'][1],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private function addRoute(string $method, string $path, array $handler): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback('/\{(\w+)\}/', static function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];

            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $path);

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'paramNames' => $paramNames,
        ];
    }
}
