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
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
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
        $GLOBALS['__wp_test_user_caps'] = [];
        $GLOBALS['__wp_test_user_can_calls'] = [];
        $GLOBALS['__wp_test_caps'] = [];
        $GLOBALS['__wp_test_cap_calls'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_user_caps'],
            $GLOBALS['__wp_test_user_can_calls'],
            $GLOBALS['__wp_test_caps'],
            $GLOBALS['__wp_test_cap_calls'],
        );
        parent::tearDown();
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
    public function authorizeAllowsANonAdministratorWhoHoldsTheCapability(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['manage_options'] = true;

        $result = $this->kernelFor(new WP_User(7), isAdmin: false)
            ->authorize(WpRestKernelTestController::class, 'adminAction', $this->request());

        self::assertTrue($result);
    }

    #[Test]
    public function authorizeForbidsAnAdministratorWithoutTheCapability(): void
    {
        $result = $this->kernelFor(new WP_User(1), isAdmin: true)
            ->authorize(WpRestKernelTestController::class, 'adminAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('forbidden', $result->get_error_code());
        self::assertSame(403, $result->get_error_data()['status']);
    }

    #[Test]
    public function authorizeRequiresEveryListedCapability(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['edit_posts'] = true;

        $result = $this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'twoCapabilitiesAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('forbidden', $result->get_error_code());

        $GLOBALS['__wp_test_user_caps'][7]['publish_posts'] = true;

        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'twoCapabilitiesAction', $this->request()));
    }

    #[Test]
    public function authorizePassesTheInstanceIdAsTheMetaCapabilityObjectId(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['edit_post'] = true;

        $result = $this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'metaCapabilityAction', $this->request());

        self::assertTrue($result);
        self::assertSame(
            [['user' => 7, 'capability' => 'edit_post', 'args' => [42]]],
            $GLOBALS['__wp_test_user_can_calls'],
        );
    }

    #[Test]
    public function authorizePrefersTheInstanceIdOfARequirementOverTheAttribute(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['edit_post'] = true;

        $this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'requirementObjectAction', $this->request());

        self::assertSame([99], $GLOBALS['__wp_test_user_can_calls'][0]['args']);
    }

    #[Test]
    public function authorizeChecksTheResolvedUserNotTheSessionUser(): void
    {
        // The session user holds the capability; the token user the
        // authenticator resolved does not. The resolved user decides.
        $GLOBALS['__wp_test_caps']['manage_options'] = true;
        $GLOBALS['__wp_test_user_caps'][1]['manage_options'] = true;

        $result = $this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'adminAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame(7, $GLOBALS['__wp_test_user_can_calls'][0]['user']);
        self::assertSame([], $GLOBALS['__wp_test_cap_calls'] ?? []);
    }

    #[Test]
    public function authorizeResolvesADefinitionRequirementToItsCapability(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['manage_reports'] = true;

        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'definitionAction', $this->request()));
        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'definitionClassAction', $this->request()));
    }

    #[Test]
    public function authorizeResolvesADefinitionClassFromTheContainer(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['approve_quotes'] = true;

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === WpRestKernelNeedyDefinition::class);
        $container->method('get')->willReturn(new WpRestKernelNeedyDefinition('approve_quotes'));

        self::assertTrue($this->kernelFor(new WP_User(7), container: $container)
            ->authorize(WpRestKernelTestController::class, 'needyDefinitionAction', $this->request()));
    }

    #[Test]
    public function authorizeDeniesARequirementItCannotResolve(): void
    {
        // The definition class needs a constructor argument and is not in the
        // container: the kernel cannot tell which capability it stands for, so
        // it denies instead of skipping the requirement.
        $result = $this->kernelFor(new WP_User(7))
            ->authorize(WpRestKernelTestController::class, 'needyDefinitionAction', $this->request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('forbidden', $result->get_error_code());
        self::assertSame([], $GLOBALS['__wp_test_user_can_calls'] ?? []);
    }

    #[Test]
    public function authorizeNeverConsultsIsAdminForCapabilities(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['manage_options'] = true;

        $authenticator = $this->createMock(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn(new WP_User(7));
        $authenticator->expects($this->never())->method('isAdmin');

        $kernel = new WpRestKernel($this->createStub(ContainerInterface::class), $authenticator);

        self::assertTrue($kernel->authorize(WpRestKernelTestController::class, 'adminAction', $this->request()));
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

    private function kernelFor(WP_User $user, bool $isAdmin = false, ?ContainerInterface $container = null): WpRestKernel
    {
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn($user);
        $authenticator->method('isAdmin')->willReturn($isAdmin);

        return new WpRestKernel($container ?? $this->createStub(ContainerInterface::class), $authenticator);
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

    #[Auth(capabilities: ['edit_posts', 'publish_posts'])]
    public function twoCapabilitiesAction(): WP_REST_Response
    {
        return RestResponse::success(['both' => true]);
    }

    #[Auth(capabilities: ['edit_post'], instanceId: 42)]
    public function metaCapabilityAction(): WP_REST_Response
    {
        return RestResponse::success(['meta' => true]);
    }

    #[Auth(instanceId: 42, requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => 99])])]
    public function requirementObjectAction(): WP_REST_Response
    {
        return RestResponse::success(['requirement' => true]);
    }

    #[Auth(requirements: [new WpRestKernelReportsDefinition()])]
    public function definitionAction(): WP_REST_Response
    {
        return RestResponse::success(['definition' => true]);
    }

    #[Auth(requirements: [WpRestKernelReportsDefinition::class])]
    public function definitionClassAction(): WP_REST_Response
    {
        return RestResponse::success(['definition-class' => true]);
    }

    #[Auth(requirements: [WpRestKernelNeedyDefinition::class])]
    public function needyDefinitionAction(): WP_REST_Response
    {
        return RestResponse::success(['needy' => true]);
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

/**
 * Capability definition the kernel can build with a zero-argument `new`.
 *
 * @internal
 */
final class WpRestKernelReportsDefinition implements CapabilityDefinitionInterface
{
    public function capabilityReference(): CapabilityReference
    {
        return new CapabilityReference('manage_reports');
    }

    public function capabilityOptions(): array
    {
        return [];
    }
}

/**
 * Capability definition with a required constructor argument: only the
 * container can build it.
 *
 * @internal
 */
final readonly class WpRestKernelNeedyDefinition implements CapabilityDefinitionInterface
{
    public function __construct(private string $capability) {}

    public function capabilityReference(): CapabilityReference
    {
        return new CapabilityReference($this->capability);
    }

    public function capabilityOptions(): array
    {
        return [];
    }
}
