<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook;

use InvalidArgumentException;
use Middag\WordPress\Exception\HookRegistrationException;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Tests\Hook\Fixture\AccessControlHook;
use Middag\WordPress\Tests\Hook\Fixture\Admin\MenuHooks;
use Middag\WordPress\Tests\Hook\Fixture\DemoHooks;
use Middag\WordPress\Tests\Hook\Fixture\NotDiscoveredService;
use Middag\WordPress\Tests\Hook\FixtureExtra\ExtraHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(HookRegistrar::class)]
final class HookRegistrarTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/Fixture';

    private const FIXTURE_NAMESPACE = 'Middag\WordPress\Tests\Hook\Fixture\\';

    private const EXTRA_DIR = __DIR__ . '/FixtureExtra';

    private const EXTRA_NAMESPACE = 'Middag\WordPress\Tests\Hook\FixtureExtra\\';

    protected function setUp(): void
    {
        $GLOBALS['__middag_test_registered_hooks'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_registered_hooks']);
    }

    #[Test]
    public function constructorRejectsAnEmptyPathMap(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one');

        new HookRegistrar();
    }

    #[Test]
    public function constructorRejectsANonExistingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('/definitely/not/a/dir');

        new HookRegistrar(hookPaths: ['Acme\\' => '/definitely/not/a/dir']);
    }

    #[Test]
    public function registerDiscoversEveryHookInterfaceRegardlessOfFilename(): void
    {
        $registrar = new HookRegistrar(hookPaths: [self::FIXTURE_NAMESPACE => self::FIXTURE_DIR]);

        $registrar->register();

        self::assertSame(
            [AccessControlHook::class, MenuHooks::class, DemoHooks::class],
            $this->registeredClasses(),
            'discovery follows the interface, so a class named *Hook registers alongside the *Hooks ones',
        );
    }

    #[Test]
    public function registerSkipsClassesThatDoNotImplementTheContract(): void
    {
        $registrar = new HookRegistrar(hookPaths: [self::FIXTURE_NAMESPACE => self::FIXTURE_DIR]);

        $registrar->register();

        self::assertNotContains(
            NotDiscoveredService::class,
            $this->registeredClasses(),
            'a class with a register() method but no HookInterface is not a hook',
        );
    }

    #[Test]
    public function registerScansEveryConfiguredPath(): void
    {
        $registrar = new HookRegistrar(hookPaths: [
            self::FIXTURE_NAMESPACE => self::FIXTURE_DIR,
            self::EXTRA_NAMESPACE => self::EXTRA_DIR,
        ]);

        $registrar->register();

        $registered = $this->registeredClasses();

        self::assertContains(ExtraHooks::class, $registered, 'the second root is scanned too');
        self::assertContains(DemoHooks::class, $registered, 'the first root keeps working');
    }

    #[Test]
    public function registerNamesTheHookItCannotBuildWithoutTheContainer(): void
    {
        $registrar = new HookRegistrar(hookPaths: [
            'Middag\WordPress\Tests\Hook\FixtureNeedy\\' => __DIR__ . '/FixtureNeedy',
        ]);

        $this->expectException(HookRegistrationException::class);
        $this->expectExceptionMessage('NeedyHooks requires constructor arguments');

        $registrar->register();
    }

    #[Test]
    public function registerResolvesHooksThroughTheContainerWhenAvailable(): void
    {
        $containerInstance = new DemoHooks();

        $container = new class($containerInstance) implements ContainerInterface {
            public function __construct(private readonly DemoHooks $instance) {}

            public function get(string $id): mixed
            {
                return $this->instance;
            }

            public function has(string $id): bool
            {
                return $id === DemoHooks::class;
            }
        };

        $registrar = new HookRegistrar($container, [self::FIXTURE_NAMESPACE => self::FIXTURE_DIR]);

        $registrar->register();

        self::assertContains(
            spl_object_hash($containerInstance) . ':' . DemoHooks::class,
            $GLOBALS['__middag_test_registered_hooks'],
            'the container-provided instance is the one registered',
        );
    }

    /**
     * @return list<string>
     */
    private function registeredClasses(): array
    {
        $registered = array_map(
            static fn (string $entry): string => explode(':', $entry, 2)[1],
            $GLOBALS['__middag_test_registered_hooks'],
        );
        sort($registered);

        return $registered;
    }
}
