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

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\RestControllerInterface;
use Middag\WordPress\Http\Routing\RestRouteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(RestRouteRegistrar::class)]
final class RestRouteRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RestRouteRegistrar touches no WordPress globals; isolate the static
        // recorders on the local support doubles instead.
        RouteRegistrarTestController::$namespaces = [];
        RouteRegistrarTestController::$constructions = 0;
        RouteRegistrarTestPlainClass::$constructions = 0;
    }

    #[Test]
    public function getNamespaceDerivesFromComponentNameWithDefaultVersion(): void
    {
        $registrar = new RestRouteRegistrar($this->createStub(ContainerInterface::class), $this->context('acme'));

        self::assertSame('acme/v1', $registrar->getNamespace());
    }

    #[Test]
    public function getNamespaceHonoursTheConfiguredApiVersion(): void
    {
        $registrar = new RestRouteRegistrar($this->createStub(ContainerInterface::class), $this->context('acme'), 'v2');

        self::assertSame('acme/v2', $registrar->getNamespace());
    }

    #[Test]
    public function registerDoesNothingWhenNoControllersAreRegistered(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('has');
        $container->expects($this->never())->method('get');

        $registrar = new RestRouteRegistrar($container, $this->context('acme'));
        $registrar->register();

        self::assertSame([], RouteRegistrarTestController::$namespaces);
    }

    #[Test]
    public function registerResolvesAControllerFromTheContainerWhenItIsAvailable(): void
    {
        $controller = new RouteRegistrarTestController();
        // Ignore the manual construction above; only the register() path matters.
        RouteRegistrarTestController::$constructions = 0;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(RouteRegistrarTestController::class)->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with(RouteRegistrarTestController::class)
            ->willReturn($controller);

        $registrar = new RestRouteRegistrar($container, $this->context('acme'), 'v2');
        $registrar->addController(RouteRegistrarTestController::class);
        $registrar->register();

        self::assertSame(['acme/v2'], RouteRegistrarTestController::$namespaces);
        self::assertSame(0, RouteRegistrarTestController::$constructions);
    }

    #[Test]
    public function registerInstantiatesTheControllerWhenItIsNotInTheContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(RouteRegistrarTestController::class)->willReturn(false);
        $container->expects($this->never())->method('get');

        $registrar = new RestRouteRegistrar($container, $this->context('acme'));
        $registrar->addController(RouteRegistrarTestController::class);
        $registrar->register();

        self::assertSame(1, RouteRegistrarTestController::$constructions);
        self::assertSame(['acme/v1'], RouteRegistrarTestController::$namespaces);
    }

    #[Test]
    public function registerSkipsAContainerServiceThatIsNotARestController(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new RouteRegistrarTestPlainClass());

        $registrar = new RestRouteRegistrar($container, $this->context('acme'));
        $registrar->addController(RouteRegistrarTestPlainClass::class);
        $registrar->register();

        self::assertSame([], RouteRegistrarTestController::$namespaces);
    }

    #[Test]
    public function registerSkipsAnInstantiatedClassThatIsNotARestController(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->expects($this->never())->method('get');

        $registrar = new RestRouteRegistrar($container, $this->context('acme'));
        $registrar->addController(RouteRegistrarTestPlainClass::class);
        $registrar->register();

        self::assertSame(1, RouteRegistrarTestPlainClass::$constructions);
        self::assertSame([], RouteRegistrarTestController::$namespaces);
    }

    #[Test]
    public function registerProcessesEveryRegisteredControllerWithTheDerivedNamespace(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            static fn (): RouteRegistrarTestController => new RouteRegistrarTestController(),
        );

        $registrar = new RestRouteRegistrar($container, $this->context('acme'), 'v2');
        $registrar->addController(RouteRegistrarTestController::class);
        $registrar->addController(RouteRegistrarTestController::class);
        $registrar->register();

        self::assertSame(['acme/v2', 'acme/v2'], RouteRegistrarTestController::$namespaces);
        self::assertSame(2, RouteRegistrarTestController::$constructions);
    }

    private function context(string $componentName = 'acme'): HostComponentContextInterface
    {
        $context = $this->createStub(HostComponentContextInterface::class);
        $context->method('componentName')->willReturn($componentName);

        return $context;
    }
}

/**
 * REST controller double: records the namespace passed to registerRoutes() and
 * counts constructions so the container-resolve vs. new-instantiation branches
 * of RestRouteRegistrar::register() can be told apart.
 *
 * @internal
 */
final class RouteRegistrarTestController implements RestControllerInterface
{
    /** @var list<string> */
    public static array $namespaces = [];

    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    public function registerRoutes(string $namespace): void
    {
        self::$namespaces[] = $namespace;
    }
}

/**
 * Plain class that does NOT implement RestControllerInterface, used to exercise
 * the instanceof skip branch.
 *
 * @internal
 */
final class RouteRegistrarTestPlainClass
{
    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }
}
