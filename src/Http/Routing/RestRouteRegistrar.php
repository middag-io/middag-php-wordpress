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

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Http\Contract\RestControllerInterface;
use Middag\WordPress\Http\ControllerResolver;
use Psr\Container\ContainerInterface;

/**
 * REST routing surface: collects {@see RestControllerInterface} controllers and
 * hands each the per-component namespace on register().
 *
 * The namespace is derived from the host component — `{componentName}/{version}`
 * (default version `v1`) — instead of a hardcoded brand literal, so two plugins
 * in the same request expose disjoint `/wp-json/{slug}/v1/*` roots. The optional
 * version keeps the escape hatch the old free-form namespace param offered.
 *
 * Sibling surfaces: {@see AdminRouteRegistrar} (wp-admin
 * menus) and {@see PublicRouteRegistrar} (front-end rewrite).
 *
 * @api
 */
final class RestRouteRegistrar
{
    /**
     * @var array<class-string<RestControllerInterface>>
     */
    private array $controllers = [];

    private readonly string $apiNamespace;

    public function __construct(
        private readonly ContainerInterface $container,
        HostComponentContextInterface $context,
        string $apiVersion = 'v1',
    ) {
        $this->apiNamespace = $context->componentName() . '/' . $apiVersion;
    }

    public function addController(string $controllerClass): void
    {
        $this->controllers[] = $controllerClass;
    }

    public function register(): void
    {
        foreach ($this->controllers as $controllerClass) {
            $controller = ControllerResolver::resolve($this->container, $controllerClass);

            if ($controller instanceof RestControllerInterface) {
                $controller->registerRoutes($this->apiNamespace);
            }
        }
    }

    public function getNamespace(): string
    {
        return $this->apiNamespace;
    }
}
