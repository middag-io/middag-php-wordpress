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

use Middag\Framework\Http\Contract\RouteLoaderInterface;
use Middag\Framework\Http\Routing\RouteLoader;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\WpRestKernel;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Attribute-driven REST routing surface: the declarative counterpart of the
 * imperative {@see RestRouteRegistrar}.
 *
 * Controllers declare their routes with Symfony `#[Route]` attributes (the same
 * shape the admin surface already uses) instead of an imperative
 * `registerRoutes()` body. This registrar reflection-scans each controller
 * through the framework {@see RouteLoaderInterface}, translates every route to a
 * WordPress path and registers it with `register_rest_route`, delegating the
 * `permission_callback`/`callback` pair to the {@see WpRestKernel}. The
 * namespace is derived from the host component (`{componentName}/{version}`),
 * matching {@see RestRouteRegistrar} so both surfaces can coexist during a
 * controller-by-controller migration.
 *
 * @api
 */
final class RestRouteAttributeRegistrar
{
    /**
     * @var list<class-string>
     */
    private array $controllers = [];

    private readonly string $apiNamespace;

    public function __construct(
        private readonly WpRestKernel $kernel,
        HostComponentContextInterface $context,
        private readonly ContainerInterface $container,
        private readonly RouteLoaderInterface $loader = new RouteLoader(),
        string $apiVersion = 'v1',
    ) {
        $this->apiNamespace = $context->componentName() . '/' . $apiVersion;
    }

    /**
     * @param class-string $controllerClass
     */
    public function addController(string $controllerClass): void
    {
        $this->controllers[] = $controllerClass;
    }

    public function getNamespace(): string
    {
        return $this->apiNamespace;
    }

    /**
     * Flatten every registered controller's `#[Route]` attributes into WordPress
     * route specs. Kept separate from {@see register()} so the translation is
     * unit-testable without the `register_rest_route` side effect.
     *
     * @return list<array{path: string, methods: list<string>, controller: class-string, action: string}>
     */
    public function buildRoutes(): array
    {
        $specs = [];

        foreach ($this->controllers as $controllerClass) {
            $collection = new RouteCollection();
            $this->loader->loadRoutes($collection, $this->container, $controllerClass);

            foreach ($collection as $route) {
                /** @var array{0: class-string, 1: string} $controllerRef */
                $controllerRef = $route->getDefault('_controller');
                [$class, $action] = $controllerRef;

                $methods = $route->getMethods();

                $specs[] = [
                    'path' => RestPathTranslator::toWordPress($route->getPath(), $route->getRequirements()),
                    'methods' => $methods === [] ? ['GET'] : array_values($methods),
                    'controller' => $class,
                    'action' => $action,
                ];
            }
        }

        return $specs;
    }

    /**
     * Register every route with WordPress, wiring the kernel as the
     * permission/handler pair. Triggered on `rest_api_init`.
     */
    public function register(): void
    {
        foreach ($this->buildRoutes() as $spec) {
            $class = $spec['controller'];
            $action = $spec['action'];

            register_rest_route($this->apiNamespace, $spec['path'], [
                'methods' => $spec['methods'],
                'callback' => fn (WP_REST_Request $request): WP_REST_Response => $this->kernel->handle($class, $action, $request),
                'permission_callback' => fn (WP_REST_Request $request): true|WP_Error => $this->kernel->authorize($class, $action, $request),
            ]);
        }
    }
}
