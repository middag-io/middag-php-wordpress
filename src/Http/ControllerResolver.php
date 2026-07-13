<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http;

use Middag\WordPress\Admin\AdminRouteRegistrar;
use Psr\Container\ContainerInterface;

/**
 * Single source of truth for how the routing registrars turn a controller
 * class-string into an instance: prefer the plugin's PSR-11 container (so the
 * controller inherits its injected dependencies and per-component identity),
 * and construct directly only when the container does not carry it.
 *
 * Shared by {@see Routing\RestRouteRegistrar} and
 * {@see AdminRouteRegistrar} so the has-or-new policy
 * stays in one place.
 *
 * @internal
 */
final class ControllerResolver
{
    /**
     * @param class-string $controllerClass
     */
    public static function resolve(ContainerInterface $container, string $controllerClass): object
    {
        if ($container->has($controllerClass)) {
            $service = $container->get($controllerClass);
            if (is_object($service)) {
                return $service;
            }
        }

        return new $controllerClass();
    }
}
