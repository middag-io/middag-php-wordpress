<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Runtime;

use Middag\Framework\Kernel\Contract\KernelInterface;
use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Http\Contract\RouterInterface;
use Middag\WordPress\Http\Routing\RestRouteRegistrar;
use Middag\WordPress\Support\HookSupport;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * WordPress application kernel (framework {@see KernelInterface} implementation).
 *
 * Mirrors the Moodle adapter kernel with one deliberate inversion: this class
 * holds NO static state. Process-wide statics collide between plugins in one
 * request (tests/Architecture/NoStaticMutableStateTest), so the singleton
 * storage lives in the CONSUMER subclass — a static property on the product's
 * own class is per-plugin state, because every plugin subclasses under its own
 * namespace. For the same reason the Moodle boot seam (a static
 * ContainerFactory::setBuilder()) becomes the abstract {@see buildContainer()}
 * method: the product implements it, so this adapter never names a non-OSS
 * container factory and never parks a closure in static state.
 *
 * A product subclass provides two things:
 *
 *     final class AccountKernel extends Kernel
 *     {
 *         private static ?self $kernel = null;
 *
 *         protected static function kernel(): static
 *         {
 *             return self::$kernel ??= new static();
 *         }
 *
 *         protected function buildContainer(): ContainerInterface
 *         {
 *             // Delegate to the container factory of its distribution tier.
 *         }
 *     }
 *
 * boot() wires the argument-free lib registrars onto the WordPress timeline
 * whenever the product's container binds them — {@see HookRegistrar} and
 * {@see CronRegistrar} on `init` (priority 5), {@see RestRouteRegistrar} on
 * `rest_api_init` — the exact pattern every MIDDAG plugin used to hand-roll.
 * Everything product-specific (admin menu tree, shared Inertia props, route
 * definitions, integrations) belongs in {@see onBoot()}.
 *
 * @api
 */
abstract class Kernel implements KernelInterface
{
    private ?ContainerInterface $container = null;

    private bool $booted = false;

    /** @var array<string, object> runtime overrides (facade/test swap support) */
    private array $swappedInstances = [];

    final protected function __construct() {}

    // ------------------------------------------------------------------
    // Static contract (KernelInterface) — late-static-bound to the subclass
    // ------------------------------------------------------------------

    /**
     * Boot the kernel: build the product container and wire the timeline.
     *
     * Idempotent — subsequent calls are no-ops until {@see shutdown()}.
     */
    public static function init(): void
    {
        static::kernel()->boot();
    }

    /**
     * The shared PSR-11 container.
     *
     * Boots the kernel on first access.
     *
     * @throws RuntimeException when boot finished without a container
     */
    public static function container(): ContainerInterface
    {
        static::init();

        $container = static::kernel()->container;

        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException('Kernel booted but container is null. Critical initialization failure.');
        }

        return $container;
    }

    /**
     * Resolve a service (facade/service-locator path).
     *
     * Prefer constructor injection; this accessor exists for host boundaries
     * that cannot receive dependencies (hook callbacks in legacy files,
     * templates, WP-CLI glue).
     *
     * @param class-string|string $id
     *
     * @throws RuntimeException when the service cannot be resolved
     */
    public static function get(string $id): object
    {
        static::init();

        $kernel = static::kernel();

        if (isset($kernel->swappedInstances[$id])) {
            return $kernel->swappedInstances[$id];
        }

        try {
            return static::container()->get($id);
        } catch (NotFoundExceptionInterface $notFound) {
            throw new RuntimeException('Service not found in container: ' . $id, 0, $notFound);
        } catch (ContainerExceptionInterface $failure) {
            throw new RuntimeException(sprintf('Error resolving service %s: %s', $id, $failure->getMessage()), 0, $failure);
        }
    }

    /**
     * Runtime override of a container entry (testing/facade swap), effective
     * for {@see get()} lookups even after the container is compiled.
     */
    public static function swap(string $id, object $instance): void
    {
        static::kernel()->swappedInstances[$id] = $instance;
    }

    /**
     * Execute the current wp-admin request through the admin routing surface.
     *
     * @throws RuntimeException when the product container does not bind
     *                          {@see AdminRouteRegistrar}
     */
    public static function handle(): void
    {
        $registrar = static::get(AdminRouteRegistrar::class);

        if (!$registrar instanceof AdminRouteRegistrar) {
            throw new RuntimeException('AdminRouteRegistrar binding is not an AdminRouteRegistrar instance.');
        }

        $registrar->renderApp();
    }

    /**
     * Dispatch an event through the framework's event dispatcher.
     *
     * @throws RuntimeException when no dispatcher is bound or dispatch fails
     */
    public static function dispatch(object $event): object
    {
        try {
            $dispatcher = static::get(EventDispatcherInterface::class);

            if (!$dispatcher instanceof EventDispatcherInterface) {
                throw new RuntimeException('EventDispatcherInterface binding is not a dispatcher instance.');
            }

            return $dispatcher->dispatch($event);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Dispatch failed: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * The adapter router bound by the product container.
     *
     * @throws RuntimeException when the product container does not bind
     *                          RouterInterface
     */
    public static function routing(): RouterInterface
    {
        $router = static::get(RouterInterface::class);

        if (!$router instanceof RouterInterface) {
            throw new RuntimeException('RouterInterface binding is not a router instance.');
        }

        return $router;
    }

    public static function isBooted(): bool
    {
        return static::kernel()->booted;
    }

    /**
     * Reset kernel state (PHPUnit isolation): drops the container, swap map
     * and booted flag so the next {@see init()} performs a fresh boot.
     */
    public static function shutdown(): void
    {
        $kernel = static::kernel();

        $kernel->container = null;
        $kernel->booted = false;
        $kernel->swappedInstances = [];
    }

    // ------------------------------------------------------------------
    // Product seams
    // ------------------------------------------------------------------

    /**
     * Singleton storage, owned by the product subclass.
     *
     * A static property on the SUBCLASS is per-plugin state (each plugin
     * subclasses under its own namespace), which is why this abstract exists
     * instead of a static property here — see the class docblock.
     */
    abstract protected static function kernel(): static;

    /**
     * Build and compile the product's DI container.
     *
     * Typically delegates to Middag\Framework\Kernel\ContainerFactory::build()
     * with the product's BootstrapInterface (composing {@see WpBootstrap}), or
     * to a proprietary factory from the product's distribution tier.
     */
    abstract protected function buildContainer(): ContainerInterface;

    /**
     * Product wiring executed once per boot, after the standard timeline
     * hooks are registered (admin menu tree, shared props, route definitions,
     * integrations, ...).
     */
    protected function onBoot(ContainerInterface $container): void {}

    // ------------------------------------------------------------------
    // Boot process
    // ------------------------------------------------------------------

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Mark booting-in-progress so reentrant lookups (get() inside the
        // product's own boot wiring) do not recurse into boot() again.
        $this->booted = true;

        try {
            $this->container = $this->buildContainer();

            $this->wireTimeline($this->container);
            $this->onBoot($this->container);
        } catch (Throwable $throwable) {
            // Leave no half-booted singleton behind: the next init() must
            // attempt a fresh boot instead of serving a broken container.
            $this->booted = false;
            $this->container = null;

            throw $throwable;
        }
    }

    /**
     * Wire the argument-free lib registrars onto the WordPress hook timeline,
     * resolving each from the product container only when bound.
     */
    private function wireTimeline(ContainerInterface $container): void
    {
        HookSupport::addAction('init', static function () use ($container): void {
            if ($container->has(HookRegistrar::class)) {
                $registrar = $container->get(HookRegistrar::class);

                if ($registrar instanceof HookRegistrar) {
                    $registrar->register();
                }
            }

            if ($container->has(CronRegistrar::class)) {
                $registrar = $container->get(CronRegistrar::class);

                if ($registrar instanceof CronRegistrar) {
                    $registrar->register();
                }
            }
        }, 5);

        HookSupport::addAction('rest_api_init', static function () use ($container): void {
            if ($container->has(RestRouteRegistrar::class)) {
                $registrar = $container->get(RestRouteRegistrar::class);

                if ($registrar instanceof RestRouteRegistrar) {
                    $registrar->register();
                }
            }
        });
    }
}
