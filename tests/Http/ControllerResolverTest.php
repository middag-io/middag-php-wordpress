<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http;

use Middag\WordPress\Http\ControllerResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

/**
 * Direct coverage of {@see ControllerResolver}: the has-or-new resolution
 * policy shared by RestRouteRegistrar and AdminRouteRegistrar. Those registrars
 * already exercise the "container has it" and "container does not have it"
 * branches through their own tests; this file also covers the third branch —
 * the container claims to have the service but returns something that is not
 * an object — which neither of those callers happens to exercise.
 *
 * @internal
 */
#[CoversClass(ControllerResolver::class)]
final class ControllerResolverTest extends TestCase
{
    #[Test]
    public function resolvesFromTheContainerWhenItHasTheService(): void
    {
        $controller = new ControllerResolverTestController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(ControllerResolverTestController::class)->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with(ControllerResolverTestController::class)
            ->willReturn($controller);

        $resolved = ControllerResolver::resolve($container, ControllerResolverTestController::class);

        self::assertSame($controller, $resolved);
    }

    #[Test]
    public function constructsDirectlyWhenTheContainerDoesNotHaveTheService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(ControllerResolverTestController::class)->willReturn(false);
        $container->expects($this->never())->method('get');

        $resolved = ControllerResolver::resolve($container, ControllerResolverTestController::class);

        self::assertInstanceOf(ControllerResolverTestController::class, $resolved);
    }

    #[Test]
    public function constructsDirectlyWhenTheContainerClaimsToHaveItButReturnsANonObject(): void
    {
        // A misbehaving/legacy container entry (e.g. null, a scalar) must not be
        // handed back as the "resolved controller" — resolve() falls through to
        // constructing the class directly instead.
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(ControllerResolverTestController::class)->willReturn(true);
        $container->method('get')->with(ControllerResolverTestController::class)->willReturn(null);

        $resolved = ControllerResolver::resolve($container, ControllerResolverTestController::class);

        self::assertInstanceOf(ControllerResolverTestController::class, $resolved);
    }

    #[Test]
    public function resolvesAnyObjectTypeFromTheContainerRegardlessOfClassMatch(): void
    {
        // resolve()'s only contract on the container branch is is_object(); it
        // does not itself enforce that the returned service is an instance of
        // $controllerClass (that is the caller's concern, e.g. instanceof checks
        // in RestRouteRegistrar/AdminRouteRegistrar).
        $unrelated = new stdClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($unrelated);

        $resolved = ControllerResolver::resolve($container, ControllerResolverTestController::class);

        self::assertSame($unrelated, $resolved);
    }
}

/**
 * @internal
 */
final class ControllerResolverTestController {}
