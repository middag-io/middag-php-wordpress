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
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Tests\Hook\Fixture\Admin\MenuHooks;
use Middag\WordPress\Tests\Hook\Fixture\DemoHooks;
use Middag\WordPress\Tests\Hook\Fixture\NotDiscoveredService;
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

    protected function setUp(): void
    {
        $GLOBALS['__middag_test_registered_hooks'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_registered_hooks']);
    }

    #[Test]
    public function constructorRejectsANullHookDir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('null');

        new HookRegistrar();
    }

    #[Test]
    public function constructorRejectsANonExistingHookDir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('/definitely/not/a/dir');

        new HookRegistrar(hookDir: '/definitely/not/a/dir');
    }

    #[Test]
    public function registerDiscoversHooksClassesRelativeToTheExplicitHookDir(): void
    {
        $registrar = new HookRegistrar(
            hookNamespace: self::FIXTURE_NAMESPACE,
            hookDir: self::FIXTURE_DIR,
        );

        $registrar->register();

        $registered = array_map(
            static fn (string $entry): string => explode(':', $entry, 2)[1],
            $GLOBALS['__middag_test_registered_hooks'],
        );
        sort($registered);

        self::assertSame(
            [MenuHooks::class, DemoHooks::class],
            $registered,
            'both the root-level and the nested *Hooks fixtures register, with FQCNs derived from $hookDir',
        );
        self::assertNotContains(
            NotDiscoveredService::class,
            $registered,
            'files not ending in Hooks are skipped',
        );
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

        $registrar = new HookRegistrar(
            $container,
            self::FIXTURE_NAMESPACE,
            self::FIXTURE_DIR,
        );

        $registrar->register();

        self::assertContains(
            spl_object_hash($containerInstance) . ':' . DemoHooks::class,
            $GLOBALS['__middag_test_registered_hooks'],
            'the container-provided instance is the one registered',
        );
    }
}
