<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Controller;

use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Controller\AbstractWpRestController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Concrete fixture exposing the abstract controller's protected seam so the
 * coverage test can drive it directly.
 *
 * @internal
 */
final class AbstractWpRestControllerCoverageFixture extends AbstractWpRestController
{
    public function registerRoutes(string $namespace): void {}

    public function exposedGetUser(WP_REST_Request $request): ?WP_User
    {
        return $this->getUser($request);
    }

    public function exposedGetBody(WP_REST_Request $request): array
    {
        return $this->getBody($request);
    }

    public function exposedSanitizedEmail(WP_REST_Request $request, string $key, string $default = ''): string
    {
        return $this->sanitizedEmail($request, $key, $default);
    }

    public function exposedValidateRequired(array $data, array $fields): ?WP_REST_Response
    {
        return $this->validateRequired($data, $fields);
    }

    public function exposedRoute(string $namespace, string $path, string $method, callable $callback): void
    {
        $this->route($namespace, $path, $method, $callback);
    }
}

/**
 * Drives the permission callbacks, request readers, validation and route helper
 * of the abstract controller through a concrete fixture and the WP-session
 * authenticator. The sanitize seam helpers are covered by
 * {@see AbstractWpRestControllerSanitizeTest}.
 *
 * @internal
 */
#[CoversClass(AbstractWpRestController::class)]
final class AbstractWpRestControllerCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_user_id'] = 0;
        $GLOBALS['__wp_test_users_by'] = [];
        $GLOBALS['__wp_test_rest_routes'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_user_id'],
            $GLOBALS['__wp_test_users_by'],
            $GLOBALS['__wp_test_rest_routes'],
        );
    }

    #[Test]
    public function permissionCheckPassesCorsPreflight(): void
    {
        $request = new WP_REST_Request();
        $request->set_method('OPTIONS');

        self::assertTrue($this->controller()->permissionCheck($request));
    }

    #[Test]
    public function permissionCheckRejectsUnauthenticatedRequests(): void
    {
        $request = new WP_REST_Request();
        $request->set_method('GET');

        $result = $this->controller()->permissionCheck($request);
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('unauthorized', $result->get_error_code());
        self::assertSame(['status' => 401], $result->get_error_data());
    }

    #[Test]
    public function permissionCheckPassesAuthenticatedRequests(): void
    {
        $this->loginAs(5, ['subscriber']);
        $request = new WP_REST_Request();
        $request->set_method('GET');

        self::assertTrue($this->controller()->permissionCheck($request));
    }

    #[Test]
    public function permissionCheckPropagatesAnAuthenticatorError(): void
    {
        // The WP-session authenticator never returns a WP_Error, but a
        // token-backed one can (a rejected bearer token). The controller must
        // surface it verbatim rather than masking it as a generic 401.
        $request = new WP_REST_Request();
        $request->set_method('GET');

        $result = $this->controllerWith($this->rejectingAuthenticator('token_expired'))->permissionCheck($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('token_expired', $result->get_error_code());
    }

    #[Test]
    public function adminPermissionCheckRejectsUnauthenticated(): void
    {
        $request = new WP_REST_Request();
        $request->set_method('GET');

        $result = $this->controller()->adminPermissionCheck($request);
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('unauthorized', $result->get_error_code());
        self::assertSame(['status' => 401], $result->get_error_data());
    }

    #[Test]
    public function adminPermissionCheckDoesNotPassOptionsPreflight(): void
    {
        // Unlike permissionCheck, admin routes have NO OPTIONS passthrough: an
        // anonymous preflight must still be rejected, not waved through.
        $request = new WP_REST_Request();
        $request->set_method('OPTIONS');

        $result = $this->controller()->adminPermissionCheck($request);
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('unauthorized', $result->get_error_code());
    }

    #[Test]
    public function adminPermissionCheckPropagatesAnAuthenticatorError(): void
    {
        $request = new WP_REST_Request();
        $request->set_method('GET');

        $result = $this->controllerWith($this->rejectingAuthenticator('token_invalid'))->adminPermissionCheck($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('token_invalid', $result->get_error_code());
    }

    #[Test]
    public function adminPermissionCheckRejectsNonAdmins(): void
    {
        $this->loginAs(6, ['editor']);
        $request = new WP_REST_Request();
        $request->set_method('GET');

        $result = $this->controller()->adminPermissionCheck($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('forbidden', $result->get_error_code());
        self::assertSame(['status' => 403], $result->get_error_data());
    }

    #[Test]
    public function adminPermissionCheckPassesAdmins(): void
    {
        $this->loginAs(7, ['administrator']);
        $request = new WP_REST_Request();
        $request->set_method('GET');

        self::assertTrue($this->controller()->adminPermissionCheck($request));
    }

    #[Test]
    public function publicPermissionCheckAlwaysPasses(): void
    {
        self::assertTrue($this->controller()->publicPermissionCheck(new WP_REST_Request()));
    }

    #[Test]
    public function getUserReturnsTheAuthenticatedUser(): void
    {
        $this->loginAs(9, ['subscriber']);

        $user = $this->controller()->exposedGetUser(new WP_REST_Request());

        self::assertInstanceOf(WP_User::class, $user);
        self::assertSame(9, $user->ID);
    }

    #[Test]
    public function getUserReturnsNullWhenUnauthenticated(): void
    {
        self::assertNull($this->controller()->exposedGetUser(new WP_REST_Request()));
    }

    #[Test]
    public function getBodyReturnsTheJsonParams(): void
    {
        $request = new WP_REST_Request();
        $request->set_json_params(['name' => 'Ada']);

        self::assertSame(['name' => 'Ada'], $this->controller()->exposedGetBody($request));
    }

    #[Test]
    public function getBodyFallsBackToEmptyArray(): void
    {
        $request = new WP_REST_Request();
        $request->set_json_params(null);

        self::assertSame([], $this->controller()->exposedGetBody($request));
    }

    #[Test]
    public function sanitizedEmailReturnsDefaultForNonStringValues(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('email', ['not', 'a', 'string']);

        self::assertSame(
            'fallback@example.test',
            $this->controller()->exposedSanitizedEmail($request, 'email', 'fallback@example.test'),
        );
    }

    #[Test]
    public function validateRequiredReportsMissingFields(): void
    {
        $result = $this->controller()->exposedValidateRequired(['name' => ''], ['name', 'email']);

        self::assertInstanceOf(WP_REST_Response::class, $result);
    }

    #[Test]
    public function validateRequiredReturnsNullWhenAllPresent(): void
    {
        $result = $this->controller()->exposedValidateRequired(
            ['name' => 'Ada', 'email' => 'ada@example.test'],
            ['name', 'email'],
        );

        self::assertNull($result);
    }

    #[Test]
    public function routeRegistersARestRoute(): void
    {
        $this->controller()->exposedRoute('middag/v1', '/things', 'GET', static fn (): bool => true);

        self::assertCount(1, $GLOBALS['__wp_test_rest_routes']);
        self::assertSame('middag/v1', $GLOBALS['__wp_test_rest_routes'][0]['namespace']);
        self::assertSame('/things', $GLOBALS['__wp_test_rest_routes'][0]['route']);
    }

    private function controller(): AbstractWpRestControllerCoverageFixture
    {
        return $this->controllerWith(new WpSessionAuthenticator());
    }

    private function controllerWith(RequestAuthenticatorInterface $authenticator): AbstractWpRestControllerCoverageFixture
    {
        return new AbstractWpRestControllerCoverageFixture($authenticator);
    }

    private function rejectingAuthenticator(string $code): RequestAuthenticatorInterface
    {
        return new class($code) implements RequestAuthenticatorInterface {
            public function __construct(private readonly string $code) {}

            public function resolveUser(WP_REST_Request $request): WP_Error
            {
                return new WP_Error($this->code, 'Rejected.', ['status' => 401]);
            }

            public function isAdmin(WP_REST_Request $request): bool
            {
                return false;
            }
        };
    }

    /**
     * @param array<int, string> $roles
     */
    private function loginAs(int $id, array $roles): void
    {
        $user = new WP_User($id);
        $user->roles = $roles;
        $GLOBALS['__wp_test_user_id'] = $id;
        $GLOBALS['__wp_test_users_by']['id'][(string) $id] = $user;
    }
}
