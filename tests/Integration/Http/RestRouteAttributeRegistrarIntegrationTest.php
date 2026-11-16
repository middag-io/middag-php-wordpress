<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Http;

use Middag\Framework\Http\Attribute\Auth;
use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use Middag\WordPress\Http\Routing\RestRouteAttributeRegistrar;
use Middag\WordPress\Http\WpRestKernel;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Attribute\Route;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Attribute routes registered on the real REST server and dispatched through
 * the REST kernel, with the session authenticator.
 *
 * @internal
 */
#[CoversClass(RestRouteAttributeRegistrar::class)]
#[CoversClass(WpRestKernel::class)]
#[CoversClass(WpSessionAuthenticator::class)]
final class RestRouteAttributeRegistrarIntegrationTest extends TestCase
{
    private static int $subscriberId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $userId = wp_insert_user([
            'user_login' => 'middaglib_it_subscriber',
            'user_pass' => wp_generate_password(),
            'user_email' => 'subscriber@example.org',
            'role' => 'subscriber',
        ]);
        self::assertIsInt($userId);
        self::$subscriberId = $userId;

        $container = new Container();
        $registrar = new RestRouteAttributeRegistrar(
            new WpRestKernel($container, new WpSessionAuthenticator()),
            new WpComponentContext('middaglib-it', '1.0.0'),
            $container,
        );
        $registrar->addController(IntegrationRestController::class);

        // A fresh server fires rest_api_init, where WordPress expects routes.
        $GLOBALS['wp_rest_server'] = null;
        add_action('rest_api_init', $registrar->register(...));
        self::assertInstanceOf(WP_REST_Server::class, rest_get_server());
    }

    public static function tearDownAfterClass(): void
    {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user(self::$subscriberId);
        $GLOBALS['wp_rest_server'] = null;
        remove_all_actions('rest_api_init');
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    #[Test]
    public function routesAreRegisteredUnderTheComponentNamespace(): void
    {
        $routes = rest_get_server()->get_routes('middaglib-it/v1');

        self::assertArrayHasKey('/middaglib-it/v1/ping', $routes);
        self::assertArrayHasKey('/middaglib-it/v1/items/(?P<id>\d+)', $routes);
        self::assertContains('middaglib-it/v1', rest_get_server()->get_namespaces());
    }

    #[Test]
    public function aPublicRouteAnswersAnonymousRequests(): void
    {
        $response = rest_do_request(new WP_REST_Request('GET', '/middaglib-it/v1/ping'));

        self::assertSame(200, $response->get_status());
        self::assertSame(['pong' => true], $response->get_data()['data'] ?? null);
    }

    #[Test]
    public function aProtectedRouteRejectsAnonymousRequests(): void
    {
        $response = rest_do_request(new WP_REST_Request('GET', '/middaglib-it/v1/items/5'));

        self::assertSame(401, $response->get_status());
    }

    #[Test]
    public function aProtectedRouteRejectsAUserWithoutTheCapability(): void
    {
        wp_set_current_user(self::$subscriberId);

        $response = rest_do_request(new WP_REST_Request('GET', '/middaglib-it/v1/items/5'));

        self::assertSame(403, $response->get_status());
    }

    #[Test]
    public function aProtectedRouteAnswersAUserWithTheCapability(): void
    {
        wp_set_current_user(1);

        $response = rest_do_request(new WP_REST_Request('GET', '/middaglib-it/v1/items/5'));

        self::assertSame(200, $response->get_status());
        self::assertSame(['id' => '5'], $response->get_data()['data'] ?? null);
    }

    #[Test]
    public function aRouteThatThrowsAnswersAnInternalError(): void
    {
        wp_set_current_user(1);

        $response = rest_do_request(new WP_REST_Request('POST', '/middaglib-it/v1/items/5'));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(500, $response->get_status());
    }
}

/**
 * Attribute-routed controller of the integration suite.
 *
 * @internal
 */
final class IntegrationRestController
{
    /**
     * @return array{pong: bool}
     */
    #[Route('/ping', methods: ['GET'])]
    #[Auth(login: false)]
    public function ping(): array
    {
        return ['pong' => true];
    }

    /**
     * @return array{id: string}
     */
    #[Route('/items/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Auth(capabilities: ['manage_options'])]
    public function show(string $id): array
    {
        return ['id' => $id];
    }

    #[Route('/items/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Auth(capabilities: ['manage_options'])]
    public function update(): never
    {
        throw new RuntimeException('boom');
    }
}
