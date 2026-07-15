<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Routing;

use Middag\WordPress\Http\Contract\RouterInterface;
use Middag\WordPress\Http\Routing\WpRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[CoversClass(WpRouter::class)]
final class WpRouterTest extends TestCase
{
    private WpRouter $router;

    protected function setUp(): void
    {
        $this->router = new WpRouter();
    }

    #[Test]
    public function implementsTheAdapterContract(): void
    {
        self::assertInstanceOf(RouterInterface::class, $this->router);
    }

    #[Test]
    public function registerAddsAManualRoute(): void
    {
        $this->router->register('things_show', '/things/{id}', FixtureRoutedController::class, 'show', ['id' => '[0-9]+']);

        $route = $this->router->getRoutes()->get('things_show');

        self::assertNotNull($route);
        self::assertSame('/things/{id}', $route->getPath());
        self::assertSame([FixtureRoutedController::class, 'show'], $route->getDefault('_controller'));
        self::assertSame('[0-9]+', $route->getRequirement('id'));
    }

    #[Test]
    public function scanAnnotationsDiscoversAttributeRoutes(): void
    {
        $builder = new ContainerBuilder();

        $this->router->scanAnnotations($builder, FixtureRoutedController::class);

        $route = $this->router->getRoutes()->get('fixture_index');

        self::assertNotNull($route);
        self::assertSame('/fixtures', $route->getPath());
    }

    #[Test]
    public function scanAnnotationsAppliesTheClassLevelRouteAsAGroup(): void
    {
        $builder = new ContainerBuilder();

        $this->router->scanAnnotations($builder, FixtureGroupedController::class);

        $route = $this->router->getRoutes()->get('grouped_show');

        self::assertNotNull($route);
        self::assertSame('/grouped/{id}', $route->getPath());
    }

    #[Test]
    public function scanAnnotationsRegistersTheControllerInTheContainer(): void
    {
        $builder = new ContainerBuilder();

        $this->router->scanAnnotations($builder, FixtureRoutedController::class);

        self::assertTrue($builder->has(FixtureRoutedController::class));
    }

    #[Test]
    public function generateUrlBuildsThePathForANamedRoute(): void
    {
        $this->router->initializeContext();
        $this->router->register('things_show', '/things/{id}', FixtureRoutedController::class, 'show');

        self::assertSame('/things/42', $this->router->generateUrl('things_show', ['id' => 42]));
    }

    #[Test]
    public function generateUrlFallsBackToTheNotFoundRoute(): void
    {
        $this->router->initializeContext();
        $this->router->registerDefaultRoutes();

        self::assertSame('/404', $this->router->generateUrl('route_that_does_not_exist'));
    }

    #[Test]
    public function registerDefaultRoutesProvidesA404Route(): void
    {
        $this->router->registerDefaultRoutes();

        $route = $this->router->getRoutes()->get('route_not_found');

        self::assertNotNull($route);

        $controller = $route->getDefault('_controller');
        self::assertIsCallable($controller);

        $response = $controller();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(404, $response->getStatusCode());
    }
}

final class FixtureRoutedController
{
    #[Route('/fixtures', name: 'fixture_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('ok');
    }

    public function show(string $id): Response
    {
        return new Response('show ' . $id);
    }
}

#[Route('/grouped', name: 'grouped_')]
final class FixtureGroupedController
{
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): Response
    {
        return new Response('grouped ' . $id);
    }
}
