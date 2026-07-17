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
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Response\RestResponse;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
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
 *  - {@see handle()} resolves the controller from the container, injects the
 *    method arguments (the request plus named path parameters) and returns its
 *    {@see WP_REST_Response}, preserving the product's response envelope.
 *
 * Route middleware (`#[Middleware]`, the org-scope/RBAC gate) is NOT run here
 * yet; {@see Routing\RestRouteAttributeRegistrar} refuses to register a
 * controller that declares it, so a scope-guarded controller cannot be migrated
 * into a fail-open state before REST middleware support lands.
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
     * Route callback: resolve the controller, invoke the action, envelope it.
     */
    public function handle(string $controllerClass, string $action, WP_REST_Request $request): WP_REST_Response
    {
        try {
            $controller = ControllerResolver::resolve($this->container, $controllerClass);
            $reflection = new ReflectionMethod($controllerClass, $action);

            /** @var mixed $result */
            $result = $reflection->invokeArgs($controller, $this->resolveArguments($reflection, $request));

            return $result instanceof WP_REST_Response ? $result : RestResponse::success($result);
        } catch (Throwable) {
            return RestResponse::internalError();
        }
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
