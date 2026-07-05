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

use Middag\WordPress\Http\Contract\RestControllerInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class RouteRegistrar
{
    /**
     * @var array<class-string<RestControllerInterface>>
     */
    private array $controllers = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $apiNamespace = 'middag/v1',
    ) {}

    public function addController(string $controllerClass): void
    {
        $this->controllers[] = $controllerClass;
    }

    public function register(): void
    {
        foreach ($this->controllers as $controllerClass) {
            $controller = $this->container->has($controllerClass)
                ? $this->container->get($controllerClass)
                : new $controllerClass();

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
