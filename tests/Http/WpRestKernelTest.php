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
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
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
