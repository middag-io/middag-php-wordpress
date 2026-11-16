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
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Contract\RestRouteMiddlewareInterface;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WordPress\Support\LogSupport;
use Middag\WordPress\Support\UserSupport;
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
 *    required, a secure default), resolves the user through the injected
 *    {@see RequestAuthenticatorInterface} (the session/token seam the
 *    imperative controllers use) and requires every capability the attribute
 *    lists of that user, with `user_can()`.
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
     *
     * 1. `OPTIONS` (CORS preflight) passes.
     * 2. `login: false` passes.
     * 3. The authenticator resolves the user: a `WP_Error` is returned as is,
     *    no user, or a user with an ID that is not positive (WordPress's
     *    logged-out user is ID 0), gives `401 unauthorized`.
     * 4. Every requirement of the attribute (its `capabilities` and its
     *    `requirements`) must pass `user_can()` for the resolved user, or the
     *    result is `403 forbidden`. The resolved user may be a token user, so
     *    the check never reads the session user (`current_user_can()`).
     *    A requirement whose capability cannot be resolved is denied, never
     *    skipped. An attribute with no capability means login only.
     * 5. The object id of a meta capability is the requirement's `instanceid`
     *    option, else the attribute's `instanceId`. An `instanceid` option
     *    that is not a positive integer, or a negative `instanceId`, is
     *    denied: an explicit but invalid object id never degrades to a check
     *    without one. An `instanceId` of 0 (the default) means "no object":
     *    the capability is checked as a primitive one, and core meta
     *    capabilities (`edit_post` and the like) then map to `do_not_allow`
     *    in `map_meta_cap()`, so they are denied.
     * 6. A `Throwable` while the attribute or a capability is resolved, or
     *    while `user_can()` runs, is logged and denied with `403 forbidden`;
     *    it never leaves the permission callback as a 500.
     *
     * The `host` of a {@see CapabilityReference} is not read: the framework
     * defines no host convention, so the key goes to `user_can()` whatever the
     * host says. A key no role holds is denied;
     * a multisite super admin passes any key except `do_not_allow`, which
     * grants nothing a super admin does not already hold.
     */
    public function authorize(string $controllerClass, string $action, WP_REST_Request $request): true|WP_Error
    {
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }

        try {
            $auth = $this->authFor($controllerClass, $action);
        } catch (Throwable $throwable) {
            return $this->denyOnFailure($throwable, $controllerClass, $action);
        }

        if (!$auth->login) {
            return true;
        }

        $user = $this->authenticator->resolveUser($request);

        if ($user instanceof WP_Error) {
            return $user;
        }

        if (!$user instanceof WP_User || $user->ID <= 0) {
            return new WP_Error('unauthorized', 'User not authenticated.', ['status' => 401]);
        }

        foreach ($auth->requirements as $requirement) {
            try {
                $isMet = $this->userMeets($user, $requirement, $auth->instanceId);
            } catch (Throwable $throwable) {
                return $this->denyOnFailure($throwable, $controllerClass, $action);
            }

            if (!$isMet) {
                return $this->forbidden();
            }
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

    private function forbidden(): WP_Error
    {
        return new WP_Error('forbidden', 'Access denied.', ['status' => 403]);
    }

    /**
     * Deny a request whose authorization subject could not be resolved, and
     * log why. Fails closed: the throw becomes a 403, never a 500. A logger
     * that throws in turn is ignored, so logging cannot reopen the 500.
     */
    private function denyOnFailure(Throwable $throwable, string $controllerClass, string $action): WP_Error
    {
        try {
            LogSupport::resolve($this->container, 'wordpress', 'http')->error(
                'REST authorization failed while resolving the route requirements; the request is denied.',
                ['exception' => $throwable, 'controller' => $controllerClass, 'action' => $action],
            );
        } catch (Throwable) {
            // The request is already denied; a broken logger must not turn the denial into a 500.
        }

        return $this->forbidden();
    }

    /**
     * Whether the user holds the capability a requirement stands for, with the
     * meta-capability object id when there is one. Fails closed: a requirement
     * with no resolvable capability, or with an invalid object id, is not met.
     */
    private function userMeets(WP_User $user, CapabilityRequirement $requirement, int $instanceId): bool
    {
        $capability = $this->capabilityOf($requirement);
        $objectId = $this->objectIdOf($requirement, $instanceId);

        if ($capability === null || $objectId === null) {
            return false;
        }

        return $objectId > 0
            ? UserSupport::userCan($user, $capability, $objectId)
            : UserSupport::userCan($user, $capability);
    }

    /**
     * The meta-capability object id of a requirement: its `instanceid` option
     * when set, else the attribute's `instanceId`. Zero means "no object".
     * Null means an explicit but invalid id (an option that is not a positive
     * integer, or a negative attribute id), which the caller denies.
     */
    private function objectIdOf(CapabilityRequirement $requirement, int $instanceId): ?int
    {
        if (!isset($requirement->options['instanceid'])) {
            return $instanceId >= 0 ? $instanceId : null;
        }

        $option = $requirement->options['instanceid'];

        if (!is_int($option) && !is_string($option)) {
            return null;
        }

        $objectId = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($objectId) ? $objectId : null;
    }

    /**
     * The WordPress capability a requirement stands for: its reference key,
     * else the reference of its definition object, else the reference of its
     * definition class, built from the container (or with a zero-argument `new`
     * when not registered). Null when none of them yields a non-empty key.
     */
    private function capabilityOf(CapabilityRequirement $requirement): ?string
    {
        $capability = $requirement->key()
            ?? ($requirement->definition ?? $this->buildDefinition($requirement->definitionClass))?->capabilityReference()->key;

        return $capability !== null && $capability !== '' ? $capability : null;
    }

    private function buildDefinition(?string $definitionClass): ?CapabilityDefinitionInterface
    {
        if ($definitionClass === null || !is_a($definitionClass, CapabilityDefinitionInterface::class, true)) {
            return null;
        }

        try {
            $definition = $this->container->has($definitionClass)
                ? $this->container->get($definitionClass)
                : $this->instantiateWithoutArguments($definitionClass);
        } catch (Throwable) {
            return null;
        }

        return $definition instanceof CapabilityDefinitionInterface ? $definition : null;
    }

    /**
     * @param class-string<CapabilityDefinitionInterface> $class
     */
    private function instantiateWithoutArguments(string $class): ?object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$reflection->isInstantiable() || ($constructor instanceof ReflectionMethod && $constructor->getNumberOfRequiredParameters() > 0)) {
            return null;
        }

        return $reflection->newInstance();
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
