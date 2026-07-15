<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Runtime;

use Middag\Framework\Kernel\Contract\KernelInterface;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Runtime\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_actions'] = [];
        FixtureKernel::reset();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_actions']);
        FixtureKernel::reset();
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertContains(KernelInterface::class, (array) class_implements(FixtureKernel::class));
    }

    #[Test]
    public function declaresNoStaticProperties(): void
    {
        // The singleton storage must live in the consumer subclass — a static
        // property on the lib class would collide between plugins in one
        // request (NoStaticMutableStateTest rationale).
        self::assertSame([], (new ReflectionClass(Kernel::class))->getStaticProperties());
    }

    #[Test]
    public function initBootsOnceAndIsIdempotent(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        FixtureKernel::init();
        FixtureKernel::init();

        self::assertTrue(FixtureKernel::isBooted());
        self::assertSame(1, FixtureKernel::$buildCount);
    }

    #[Test]
    public function containerReturnsTheBuiltContainer(): void
    {
        $container = new FixtureContainer([]);
        FixtureKernel::$containerToBuild = $container;

        self::assertSame($container, FixtureKernel::container());
    }

    #[Test]
    public function getResolvesFromTheContainer(): void
    {
        $service = new stdClass();
        FixtureKernel::$containerToBuild = new FixtureContainer(['service.id' => $service]);

        self::assertSame($service, FixtureKernel::get('service.id'));
    }

    #[Test]
    public function getThrowsARuntimeExceptionForUnknownServices(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service not found in container: missing.id');

        FixtureKernel::get('missing.id');
    }

    #[Test]
    public function swapOverridesAContainerEntry(): void
    {
        $original = new stdClass();
        $replacement = new stdClass();
        FixtureKernel::$containerToBuild = new FixtureContainer(['service.id' => $original]);

        FixtureKernel::swap('service.id', $replacement);

        self::assertSame($replacement, FixtureKernel::get('service.id'));
    }

    #[Test]
    public function dispatchDelegatesToTheBoundDispatcher(): void
    {
        $event = new stdClass();
        $dispatcher = new class implements EventDispatcherInterface {
            public ?object $dispatched = null;

            public function dispatch(object $event, ?string $eventName = null): object
            {
                $this->dispatched = $event;

                return $event;
            }
        };
        FixtureKernel::$containerToBuild = new FixtureContainer([EventDispatcherInterface::class => $dispatcher]);

        self::assertSame($event, FixtureKernel::dispatch($event));
        self::assertSame($event, $dispatcher->dispatched);
    }

    #[Test]
    public function dispatchWrapsFailuresInARuntimeException(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dispatch failed:');

        FixtureKernel::dispatch(new stdClass());
    }

    #[Test]
    public function bootWiresInitAndRestTimelineHooks(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        FixtureKernel::init();

        self::assertArrayHasKey('init', $GLOBALS['__wp_test_actions']);
        self::assertSame(5, $GLOBALS['__wp_test_actions']['init'][0]['priority']);
        self::assertArrayHasKey('rest_api_init', $GLOBALS['__wp_test_actions']);
    }

    #[Test]
    public function timelineInitCallbackResolvesBoundRegistrars(): void
    {
        $hookDir = sys_get_temp_dir() . '/middag-kernel-test-' . bin2hex(random_bytes(4));
        mkdir($hookDir);

        try {
            $container = new FixtureContainer([
                HookRegistrar::class => new HookRegistrar(null, 'Middag\\', $hookDir),
            ]);
            FixtureKernel::$containerToBuild = $container;

            FixtureKernel::init();
            $GLOBALS['__wp_test_actions']['init'][0]['callback']();

            self::assertContains(HookRegistrar::class, $container->resolved);
        } finally {
            rmdir($hookDir);
        }
    }

    #[Test]
    public function timelineCallbacksSkipUnboundRegistrars(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        FixtureKernel::init();

        // Must not throw when no registrar is bound.
        $GLOBALS['__wp_test_actions']['init'][0]['callback']();
        $GLOBALS['__wp_test_actions']['rest_api_init'][0]['callback']();

        self::assertTrue(FixtureKernel::isBooted());
    }

    #[Test]
    public function onBootRunsOncePerBoot(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);

        FixtureKernel::init();
        FixtureKernel::init();

        self::assertSame(1, FixtureKernel::$onBootCount);
    }

    #[Test]
    public function aFailedBootLeavesNoHalfBootedSingleton(): void
    {
        FixtureKernel::$containerToBuild = null; // FixtureKernel throws without a container

        try {
            FixtureKernel::init();
            self::fail('Expected the configured boot failure to propagate.');
        } catch (RuntimeException) {
            // expected
        }

        self::assertFalse(FixtureKernel::isBooted());

        // A later init() with a valid container must boot cleanly.
        FixtureKernel::$containerToBuild = new FixtureContainer([]);
        FixtureKernel::init();

        self::assertTrue(FixtureKernel::isBooted());
    }

    #[Test]
    public function shutdownAllowsAFreshBoot(): void
    {
        FixtureKernel::$containerToBuild = new FixtureContainer([]);
        FixtureKernel::init();

        FixtureKernel::shutdown();

        self::assertFalse(FixtureKernel::isBooted());

        FixtureKernel::init();

        self::assertTrue(FixtureKernel::isBooted());
        self::assertSame(2, FixtureKernel::$buildCount);
    }
}

/**
 * Concrete kernel double: owns the singleton storage and the container seam,
 * exactly like a product subclass would.
 */
final class FixtureKernel extends Kernel
{
    public static ?ContainerInterface $containerToBuild = null;

    public static int $buildCount = 0;

    public static int $onBootCount = 0;

    private static ?self $kernel = null;

    public static function reset(): void
    {
        self::$kernel = null;
        self::$containerToBuild = null;
        self::$buildCount = 0;
        self::$onBootCount = 0;
    }

    protected static function kernel(): static
    {
        return self::$kernel ??= new self();
    }

    protected function buildContainer(): ContainerInterface
    {
        ++self::$buildCount;

        return self::$containerToBuild ?? throw new RuntimeException('No container configured for this test.');
    }

    protected function onBoot(ContainerInterface $container): void
    {
        ++self::$onBootCount;
    }
}

/**
 * Minimal PSR-11 container backed by an id => instance map, recording every
 * resolved id so tests can assert what the kernel looked up.
 */
final class FixtureContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolved = [];

    /**
     * @param array<string, object> $services
     */
    public function __construct(private readonly array $services) {}

    public function get(string $id): object
    {
        $this->resolved[] = $id;

        return $this->services[$id]
            ?? throw new class('No entry: ' . $id) extends RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
