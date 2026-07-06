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

use Middag\WordPress\Http\Controller\BaseController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Concrete fixture exposing the abstract base controller's protected seam so
 * the coverage test can drive it directly.
 *
 * @internal
 */
final class BaseControllerCoverageFixture extends BaseController
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
 * Drives the permission callbacks, request readers, validation and route
 * helper of the abstract base controller through a concrete fixture. The
 * sanitize seam helpers are covered by {@see BaseControllerSanitizeTest}.
 *
 * @internal
 */
#[CoversClass(BaseController::class)]
final class BaseControllerCoverageTest extends TestCase
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

        self::assertInstanceOf(WP_Error::class, $this->controller()->permissionCheck($request));
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
    public function adminPermissionCheckRejectsUnauthenticated(): void
    {
        $request = new WP_REST_Request();
        $request->set_method('GET');

        self::assertInstanceOf(WP_Error::class, $this->controller()->adminPermissionCheck($request));
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

    private function controller(): BaseControllerCoverageFixture
    {
        return new BaseControllerCoverageFixture();
    }

    private function loginAs(int $id, array $roles): void
    {
        $user = new WP_User($id);
        $user->roles = $roles;
        $GLOBALS['__wp_test_user_id'] = $id;
        $GLOBALS['__wp_test_users_by']['id'][(string) $id] = $user;
    }
}
