<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Contract;

use Middag\WordPress\Http\Routing\Router;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adapter-local routing contract, the WordPress counterpart of the Moodle
 * adapter's `RouterInterface`.
 *
 * Wraps a Symfony {@see RouteCollection} and delegates attribute discovery to
 * the framework's `RouteLoaderInterface` (`#[Route]` reflection scan) so
 * controllers declare their own routes instead of a central array — see the
 * legacy array-based {@see Router}, which this
 * contract supersedes.
 *
 * @api
 */
interface RouterInterface
{
    /**
     * Initialize the routing context (base URL, host, scheme) from globals.
     */
    public function initializeContext(): void;

    /**
     * Retrieve the registered route collection.
     */
    public function getRoutes(): RouteCollection;

    /**
     * Get the current routing context (host, scheme, base path).
     */
    public function getContext(): ?RequestContext;

    /**
     * Register default system routes (e.g. 404) and global regex requirements.
     */
    public function registerDefaultRoutes(): void;

    /**
     * Manually register a route.
     *
     * @param array<string, string> $requirements regex requirements for parameters
     */
    public function register(string $name, string $path, string $controller, string $method, array $requirements = []): void;

    /**
     * Scan a controller class for `#[Route]` attributes and register the
     * discovered routes (delegated to the framework route loader).
     */
    public function scanAnnotations(ContainerInterface $container, ?string $specificClass = null): void;

    /**
     * Generate a URL path for a named route.
     *
     * @param array<string, mixed> $parameters
     */
    public function generateUrl(
        string $route,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string;
}
