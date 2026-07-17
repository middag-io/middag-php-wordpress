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

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Attribute\Middleware;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Contract\RestRouteMiddlewareInterface;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WordPress\Http\WpRestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * @internal
 */
#[CoversClass(WpRestKernel::class)]
final class WpRestKernelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RestKernelMiddlewareRecorder::$calls = [];
    }

    #[Test]
    public function authorizeAllowsOptionsPreflightWithoutResolvingAUser(): void
    {
        $authenticator = $this->createMock(RequestAuthenticatorInterface::class);
        $authenticator->expects($this->never())->method('resolveUser');

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);

        self::assertTrue($kernel->authorize(WpRestKernelTestController::class, 'authedAction', $this->request('OPTIONS')));
    }

    #[Test]
    public function authorizeAllowsAPublicRouteWithoutResolvingAUser(): void
    {
        $authenticator = $this->createMock(RequestAuthenticatorInterface::class);
        $authenticator->expects($this->never())->method('resolveUser');

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);

        self::assertTrue($kernel->authorize(WpRestKernelTestController::class, 'publicAction', $this->request()));
    }

    #[Test]
    public function authorizeRejectsAnAnonymousRequestOnALoginRequiredRoute(): void
    {
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn(null);

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);
        $result = $kernel->authorize(WpRestKernelTestController::class, 'authedAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('unauthorized', $result->get_error_code());
    }

    #[Test]
    public function authorizeSurfacesAnAuthenticatorError(): void
    {
        $error = new WP_Error('token_expired', 'Token expired.', ['status' => 401]);
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn($error);

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);

        self::assertSame($error, $kernel->authorize(WpRestKernelTestController::class, 'authedAction', $this->request()));
    }

    #[Test]
    public function authorizeAllowsAnAuthenticatedUser(): void
    {
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn(new WP_User(7));

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);

        self::assertTrue($kernel->authorize(WpRestKernelTestController::class, 'authedAction', $this->request()));
    }

    #[Test]
    public function authorizeForbidsANonAdminWhenTheRouteRequiresCapabilities(): void
    {
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn(new WP_User(7));
        $authenticator->method('isAdmin')->willReturn(false);

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);
        $result = $kernel->authorize(WpRestKernelTestController::class, 'adminAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('forbidden', $result->get_error_code());
    }

    #[Test]
    public function handleReturnsTheControllerResponseUnchanged(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $response = $kernel->handle(WpRestKernelTestController::class, 'authedAction', $this->request());

        self::assertSame(200, $response->get_status());
        self::assertIsArray($response->get_data());
        self::assertTrue($response->get_data()['data']['authed']);
    }

    #[Test]
    public function handleEnvelopesANonResponseResult(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $response = $kernel->handle(WpRestKernelTestController::class, 'arrayAction', $this->request());

        self::assertSame(200, $response->get_status());
        self::assertTrue($response->get_data()['success']);
        self::assertSame(['plain' => true], $response->get_data()['data']);
    }

    #[Test]
    public function handleInjectsTheRequestArgument(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $response = $kernel->handle(WpRestKernelTestController::class, 'echoMethod', $this->request('DELETE'));

        self::assertSame('DELETE', $response->get_data()['data']['method']);
    }

    #[Test]
    public function handleReturnsAnInternalErrorWhenTheActionThrows(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $response = $kernel->handle(WpRestKernelTestController::class, 'boomAction', $this->request());

        self::assertSame(500, $response->get_status());
        self::assertFalse($response->get_data()['success']);
    }

    #[Test]
    public function handleRunsWithoutMiddlewareWhenNoneIsDeclared(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $kernel->handle(MiddlewarePipelineTestController::class, 'plainAction', $this->request());

        self::assertSame(['action'], RestKernelMiddlewareRecorder::$calls);
    }

    #[Test]
    public function handleLetsAMiddlewareShortCircuitBeforeTheAction(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $response = $kernel->handle(MiddlewarePipelineTestController::class, 'shortCircuited', $this->request());

        self::assertSame(403, $response->get_status());
        // The short-circuit ran; the action never did.
        self::assertSame(['short-circuit'], RestKernelMiddlewareRecorder::$calls);
    }

    #[Test]
    public function handleComposesClassMiddlewareOutsideMethodMiddleware(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));
        $kernel->handle(ClassMiddlewareTestController::class, 'ordered', $this->request());

        self::assertSame(
            ['outer:before', 'inner:before', 'action', 'inner:after', 'outer:after'],
            RestKernelMiddlewareRecorder::$calls,
        );
    }

    #[Test]
    public function handleResolvesMiddlewareFromTheContainerWhenRegistered(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => $id === ConstructorRestMiddleware::class,
        );
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => new ConstructorRestMiddleware('X'),
        );

        $kernel = new WpRestKernel($container, $this->createStub(RequestAuthenticatorInterface::class));
        $kernel->handle(MiddlewarePipelineTestController::class, 'containerResolved', $this->request());

        // Constructor-arg middleware cannot be `new`'d — proves the container path.
        self::assertSame(['ctor:X', 'action'], RestKernelMiddlewareRecorder::$calls);
    }

    #[Test]
    public function handleFailsLoudWhenAMiddlewareViolatesTheContract(): void
    {
        $kernel = new WpRestKernel($this->container(), $this->createStub(RequestAuthenticatorInterface::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        $kernel->handle(MiddlewarePipelineTestController::class, 'misconfigured', $this->request());
    }

    private function request(string $method = 'GET'): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_method($method);

        return $request;
    }

    private function container(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        return $container;
    }
}

/**
 * Controller double exercising the kernel's auth modes + return-value handling.
 *
 * @internal
 */
final class WpRestKernelTestController
{
    #[Auth(login: false)]
    public function publicAction(): WP_REST_Response
    {
        return RestResponse::success(['public' => true]);
    }

    public function authedAction(): WP_REST_Response
    {
        return RestResponse::success(['authed' => true]);
    }

    #[Auth(capabilities: ['manage_options'])]
    public function adminAction(): WP_REST_Response
    {
        return RestResponse::success(['admin' => true]);
    }

    /**
     * @return array<string, bool>
     */
    public function arrayAction(): array
    {
        return ['plain' => true];
    }

    public function echoMethod(WP_REST_Request $request): WP_REST_Response
    {
        return RestResponse::success(['method' => $request->get_method()]);
    }

    public function boomAction(): WP_REST_Response
    {
        throw new RuntimeException('boom');
    }
}

/**
 * Ordered record of what ran during a dispatch, so the middleware pipeline tests
 * can assert both that a link ran and where in the chain it sat.
 *
 * @internal
 */
final class RestKernelMiddlewareRecorder
{
    /** @var list<string> */
    public static array $calls = [];
}

/**
 * Denies before the action, recording that it ran (the action must not).
 *
 * @internal
 */
final class ShortCircuitRestMiddleware implements RestRouteMiddlewareInterface
{
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'short-circuit';

        return RestResponse::forbidden('Denied.');
    }
}

/**
 * Wraps the chain, recording before/after so ordering is observable.
 *
 * @internal
 */
final class OuterRestMiddleware implements RestRouteMiddlewareInterface
{
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'outer:before';
        $response = $next($request);
        RestKernelMiddlewareRecorder::$calls[] = 'outer:after';

        return $response;
    }
}

/**
 * @internal
 */
final class InnerRestMiddleware implements RestRouteMiddlewareInterface
{
    public function process(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'inner:before';
        $response = $next($request);
        RestKernelMiddlewareRecorder::$calls[] = 'inner:after';

        return $response;
    }
}

/**
 * Requires a constructor argument, so a zero-argument `new` cannot build it —
 * only the container can, which is exactly what the resolution test asserts.
 *
 * @internal
 */
final readonly class ConstructorRestMiddleware implements RestRouteMiddlewareInterface
{
    public function __construct(private string $tag) {}

    public function process(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'ctor:' . $this->tag;

        return $next($request);
    }
}

/**
 * Does NOT implement {@see RestRouteMiddlewareInterface} — the fail-loud case.
 *
 * @internal
 */
final class NotARestMiddleware {}

/**
 * Controller double exercising the `#[Middleware]` pipeline.
 *
 * @internal
 */
final class MiddlewarePipelineTestController
{
    public function plainAction(): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'action';

        return RestResponse::success(['plain' => true]);
    }

    #[Middleware(ShortCircuitRestMiddleware::class)]
    public function shortCircuited(): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'action';

        return RestResponse::success(['reached' => true]);
    }

    #[Middleware(ConstructorRestMiddleware::class)]
    public function containerResolved(): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'action';

        return RestResponse::success();
    }

    #[Middleware(NotARestMiddleware::class)]
    public function misconfigured(): WP_REST_Response
    {
        return RestResponse::success();
    }
}

/**
 * Class-level middleware wraps method-level middleware: proves both attribute
 * levels run and that the class level sits outermost.
 *
 * @internal
 */
#[Middleware(OuterRestMiddleware::class)]
final class ClassMiddlewareTestController
{
    #[Middleware(InnerRestMiddleware::class)]
    public function ordered(): WP_REST_Response
    {
        RestKernelMiddlewareRecorder::$calls[] = 'action';

        return RestResponse::success();
    }
}
