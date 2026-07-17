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

use Middag\Framework\Http\Attribute\Middleware;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Routing\RestRouteAttributeRegistrar;
use Middag\WordPress\Http\WpRestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[CoversClass(RestRouteAttributeRegistrar::class)]
final class RestRouteAttributeRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_test_rest_routes'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_rest_routes']);
        parent::tearDown();
    }

    #[Test]
    public function getNamespaceDerivesFromComponentName(): void
    {
        self::assertSame('acme/v1', $this->registrar()->getNamespace());
    }

    #[Test]
    public function buildRoutesTranslatesAttributesToWordPressSpecs(): void
    {
        $registrar = $this->registrar();
        $registrar->addController(AttributeRoutedTestController::class);

        $specs = $registrar->buildRoutes();

        self::assertCount(2, $specs);

        $byPath = [];
        foreach ($specs as $spec) {
            $byPath[$spec['path']] = $spec;
        }

        self::assertArrayHasKey('/things', $byPath);
        self::assertSame(['GET'], $byPath['/things']['methods']);
        self::assertSame(AttributeRoutedTestController::class, $byPath['/things']['controller']);
        self::assertSame('index', $byPath['/things']['action']);

        self::assertArrayHasKey('/things/(?P<id>\d+)', $byPath);
        self::assertSame(['POST'], $byPath['/things/(?P<id>\d+)']['methods']);
        self::assertSame('update', $byPath['/things/(?P<id>\d+)']['action']);
    }

    #[Test]
    public function registerRegistersEveryRouteWithTheDerivedNamespace(): void
    {
        $registrar = $this->registrar();
        $registrar->addController(AttributeRoutedTestController::class);

        $registrar->register();

        $routes = $GLOBALS['__wp_test_rest_routes'];
        self::assertCount(2, $routes);

        foreach ($routes as $route) {
            self::assertSame('acme/v1', $route['namespace']);
            self::assertArrayHasKey('methods', $route['args']);
            self::assertIsCallable($route['args']['callback']);
            self::assertIsCallable($route['args']['permission_callback']);
        }
    }

    #[Test]
    public function buildRoutesRefusesAControllerThatDeclaresMiddleware(): void
    {
        $registrar = $this->registrar();
        $registrar->addController(MiddlewareGuardedTestController::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not run route middleware/');

        $registrar->buildRoutes();
    }

    private function registrar(): RestRouteAttributeRegistrar
    {
        $context = $this->createStub(HostComponentContextInterface::class);
        $context->method('componentName')->willReturn('acme');

        $kernel = new WpRestKernel(
            $this->createStub(ContainerInterface::class),
            $this->createStub(RequestAuthenticatorInterface::class),
        );

        return new RestRouteAttributeRegistrar($kernel, $context, $this->createStub(ContainerInterface::class));
    }
}

/**
 * Controller double with #[Route] attributes for the happy-path scan.
 *
 * @internal
 */
final class AttributeRoutedTestController
{
    #[Route('/things', methods: ['GET'])]
    public function index(): void {}

    #[Route('/things/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(): void {}
}

/**
 * Controller double declaring #[Middleware] to exercise the fail-closed guard.
 *
 * @internal
 */
final class MiddlewareGuardedTestController
{
    #[Route('/guarded', methods: ['GET'])]
    #[Middleware('SomeScopeMiddleware')]
    public function index(): void {}
}
