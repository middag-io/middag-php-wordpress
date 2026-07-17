<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http;

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Attribute\Middleware;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Contract\RestRouteMiddlewareInterface;
use Middag\WordPress\Http\Response\RestResponse;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Dispatch kernel for attribute-routed WordPress REST controllers.
 *
 * The counterpart of the admin {@see WpHttpKernel} for the REST surface. WP
 * splits a route into a `permission_callback` (a boolean gate) and a `callback`
 * (the handler); this kernel supplies both from the controller's PHP 8
 * attributes:
 *
 *  - {@see authorize()} reads `#[Auth]` (method wins over class; absent = login
 *    required, a secure default) and gates through the injected
 *    {@see RequestAuthenticatorInterface} — the same JWT/session seam the
 *    imperative controllers used.
 *  - {@see handle()} composes the route-middleware chain declared with
 *    `#[Middleware]` (the org-scope/RBAC and rate-limit gate) around the action,
 *    resolves the controller from the container, injects the method arguments
 *    (the request plus named path parameters) and returns its
 *    {@see WP_REST_Response}, preserving the product's response envelope.
 *
 * Route middleware speaks {@see RestRouteMiddlewareInterface} (WP-REST-native),
 * not the framework's HttpFoundation contract, so a denial stays inside the REST
 * envelope; a controller wiring an entry that does not implement it fails loud at
 * dispatch (a wiring bug, not a request error).
 *
 * @api
 */
final readonly class WpRestKernel
{
    public function __construct(
        private ContainerInterface $container,
        private RequestAuthenticatorInterface $authenticator,
    ) {}

    /**
     * Permission callback: enforce the route's `#[Auth]` requirement.
     */
    public function authorize(string $controllerClass, string $action, WP_REST_Request $request): true|WP_Error
    {
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }

        $auth = $this->authFor($controllerClass, $action);

        if (!$auth->login) {
            return true;
        }

        $user = $this->authenticator->resolveUser($request);

        if ($user instanceof WP_Error) {
            return $user;
        }

        if (!$user instanceof WP_User) {
            return new WP_Error('unauthorized', 'User not authenticated.', ['status' => 401]);
        }

        if ($auth->capabilities !== [] && !$this->authenticator->isAdmin($request)) {
            return new WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Route callback: run the `#[Middleware]` chain around the action.
     *
     * The chain is resolved before the try/catch so a middleware that violates
     * {@see RestRouteMiddlewareInterface} fails loud (a wiring bug surfaced at
     * dispatch, mirroring how the framework kernel treats route middleware). Any
     * throw from the chain or the action is caught and enveloped as a 500, so a
     * request-time failure never breaks the REST response envelope.
     */
    public function handle(string $controllerClass, string $action, WP_REST_Request $request): WP_REST_Response
    {
        $middlewares = $this->resolveRouteMiddleware($controllerClass, $action);

        try {
            $pipeline = fn (WP_REST_Request $request): WP_REST_Response => $this->invokeAction($controllerClass, $action, $request);

            return $this->runRouteMiddleware($middlewares, $request, $pipeline);
        } catch (Throwable) {
            return RestResponse::internalError();
        }
    }

    /**
     * Resolve the controller, invoke the action and envelope its result — the
     * innermost link the middleware chain wraps.
     */
    private function invokeAction(string $controllerClass, string $action, WP_REST_Request $request): WP_REST_Response
    {
        $controller = ControllerResolver::resolve($this->container, $controllerClass);
        $reflection = new ReflectionMethod($controllerClass, $action);

        /** @var mixed $result */
        $result = $reflection->invokeArgs($controller, $this->resolveArguments($reflection, $request));

        return $result instanceof WP_REST_Response ? $result : RestResponse::success($result);
    }

    /**
     * Resolve the ordered route-middleware chain declared via `#[Middleware]`.
     *
     * Class-level declarations come first (outermost), then method-level, each in
     * declaration order; the attribute is repeatable so several accumulate. Each
     * entry is fetched from the container — falling back to a zero-argument `new`
     * when unregistered — and must implement {@see RestRouteMiddlewareInterface}.
     *
     * @param class-string $controllerClass
     *
     * @return list<RestRouteMiddlewareInterface>
     */
    private function resolveRouteMiddleware(string $controllerClass, string $action): array
    {
        $attributes = [
            ...(new ReflectionClass($controllerClass))->getAttributes(Middleware::class),
            ...(new ReflectionMethod($controllerClass, $action))->getAttributes(Middleware::class),
        ];

        if ($attributes === []) {
            return [];
        }

        $resolved = [];

        foreach ($attributes as $attribute) {
            foreach ($attribute->newInstance()->middleware as $id) {
                $instance = $this->container->has($id)
                    ? $this->container->get($id)
                    : (class_exists($id) ? new $id() : null);

                if (!$instance instanceof RestRouteMiddlewareInterface) {
                    throw new RuntimeException(sprintf(
                        'REST route middleware "%s" must implement %s.',
                        $id,
                        RestRouteMiddlewareInterface::class,
                    ));
                }

                $resolved[] = $instance;
            }
        }

        return $resolved;
    }

    /**
     * Compose the route-middleware chain around the action, outermost first.
     *
     * @param list<RestRouteMiddlewareInterface>          $middlewares
     * @param callable(WP_REST_Request): WP_REST_Response $action
     */
    private function runRouteMiddleware(array $middlewares, WP_REST_Request $request, callable $action): WP_REST_Response
    {
        $next = $action;

        foreach (array_reverse($middlewares) as $middleware) {
            $current = $next;
            $next = static fn (WP_REST_Request $request): WP_REST_Response => $middleware->process($request, $current);
        }

        return $next($request);
    }

    /**
     * The effective `#[Auth]` for an action: method-level wins over class-level;
     * an absent attribute defaults to "login required".
     */
    private function authFor(string $controllerClass, string $action): Auth
    {
        $methodAttributes = (new ReflectionMethod($controllerClass, $action))->getAttributes(Auth::class);

        if ($methodAttributes !== []) {
            return $methodAttributes[0]->newInstance();
        }

        $classAttributes = (new ReflectionClass($controllerClass))->getAttributes(Auth::class);

        if ($classAttributes !== []) {
            return $classAttributes[0]->newInstance();
        }

        return new Auth();
    }

    /**
     * Resolve an action's arguments: the request object by type, otherwise the
     * matching request parameter by name (falling back to the declared default).
     *
     * @return list<mixed>
     */
    private function resolveArguments(ReflectionMethod $reflection, WP_REST_Request $request): array
    {
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === WP_REST_Request::class) {
                $arguments[] = $request;

                continue;
            }

            $value = $request->get_param($parameter->getName());

            if ($value === null && $parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            $arguments[] = $value;
        }

        return $arguments;
    }
}
