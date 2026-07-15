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
use Middag\WordPress\Http\Contract\RouterInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Symfony-backed router for the WordPress adapter, the counterpart of the
 * Moodle adapter's `MoodleRouter`.
 *
 * Routes live in a {@see RouteCollection}; discovery is delegated to the
 * framework {@see RouteLoaderInterface}, which reflection-scans controllers
 * for `#[Route]` attributes (class-level attribute = path/name group). This
 * supersedes the array-based {@see Router} (deprecated): controllers declare
 * their own routes and the same collection feeds matching (`WpHttpKernel`)
 * and URL generation.
 *
 * URL generation returns Symfony PATHS (`/collaborators/42`). Composing a
 * wp-admin URL (`admin.php?page={slug}&route={path}`) is the admin surface's
 * concern — see `AdminRouteRegistrar::adminUrl()`.
 *
 * @api
 */
final class WpRouter implements RouterInterface
{
    private readonly RouteCollection $routes;

    private ?RequestContext $context = null;

    private ?UrlGenerator $generator = null;

    public function __construct(private readonly RouteLoaderInterface $loader = new RouteLoader())
    {
        $this->routes = new RouteCollection();
    }

    public function initializeContext(): void
    {
        $this->context = (new RequestContext())->fromRequest(Request::createFromGlobals());

        // Admin dispatch matches synthetic paths carried by the `route` query
        // parameter, not the physical /wp-admin/... URI — keep the base URL
        // empty so generated paths stay host-root-relative.
        $this->context->setBaseUrl('');
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function getContext(): ?RequestContext
    {
        return $this->context;
    }

    public function registerDefaultRoutes(): void
    {
        $this->routes->add('route_not_found', new Route('/404', [
            '_controller' => static fn (): Response => new Response('Page not found', 404),
        ]));

        // Global regex requirements for cleaner route definitions in attributes.
        $this->routes->addRequirements([
            'any' => '.*',
            'id' => '[0-9]+',
            'uuid' => '[0-9a-fA-F\-]{36}',
        ]);
    }

    public function register(string $name, string $path, string $controller, string $method, array $requirements = []): void
    {
        $this->routes->add($name, new Route($path, [
            '_controller' => [$controller, $method],
        ], $requirements));
    }

    public function scanAnnotations(ContainerInterface $container, ?string $specificClass = null): void
    {
        $this->loader->loadRoutes($this->routes, $container, $specificClass);
    }

    public function generateUrl(
        string $route,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if (!$this->generator instanceof UrlGenerator) {
            if (!$this->context instanceof RequestContext) {
                $this->initializeContext();
            }

            /** @var RequestContext $context */
            $context = $this->context;
            $this->generator = new UrlGenerator($this->routes, $context);
        }

        try {
            return $this->generator->generate($route, $parameters, $referenceType);
        } catch (RouteNotFoundException) {
            // Fall back to the 404 route instead of crashing the UI on a
            // broken link; registerDefaultRoutes() provides it.
            return $this->generator->generate('route_not_found', [], $referenceType);
        }
    }
}
