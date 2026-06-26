<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Cron;

use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\WordPress\Cron\CronHandler;
use Middag\WordPress\Support\LogSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * @internal
 */
#[CoversClass(CronHandler::class)]
final class CronHandlerLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        LogSupport::setLogger(null);
    }

    protected function tearDown(): void
    {
        LogSupport::setLogger(null);
    }

    #[Test]
    public function serviceNotFoundReachesTheWiredLogger(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        $container = $this->container([]);
        $callback = CronHandler::dispatch($container, 'App\Missing', 'run');
        $callback();

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertStringContainsString('Service not found', $spy->records[0]['message']);
        self::assertStringContainsString('App\Missing', $spy->records[0]['message']);
    }

    #[Test]
    public function serviceThrowingReachesTheWiredLogger(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        $service = new class {
            public function run(): never
            {
                throw new RuntimeException('stripe down');
            }
        };

        $container = $this->container([$service::class => $service]);
        $callback = CronHandler::dispatch($container, $service::class, 'run');
        $callback();

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertStringContainsString('stripe down', $spy->records[0]['message']);
    }

    #[Test]
    public function logsAreNotProducedOnSuccessfulDispatch(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        $service = new class {
            public bool $called = false;

            public function run(): void
            {
                $this->called = true;
            }
        };

        $container = $this->container([$service::class => $service]);
        $callback = CronHandler::dispatch($container, $service::class, 'run');
        $callback();

        self::assertTrue($service->called);
        self::assertSame([], $spy->records);
    }

    #[Test]
    public function primesTheLoggerFromTheContainerLoggerFactoryWhenNoneIsWired(): void
    {
        // No logger wired beforehand. The container exposes the framework's
        // LoggerFactory (the real DI binding — the framework does NOT register a
        // shared LoggerInterface), so CronHandler must prime LogSupport from it.
        self::assertNull(LogSupport::getLogger());

        $container = $this->container([LoggerFactory::class => $this->loggerFactory()]);

        $callback = CronHandler::dispatch($container, 'App\Missing', 'run');
        $callback();

        self::assertInstanceOf(LoggerInterface::class, LogSupport::getLogger());
    }

    /**
     * @param array<string, object> $services
     */
    private function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services
             */
            public function __construct(private readonly array $services) {}

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }

            public function get(string $id): object
            {
                return $this->services[$id];
            }
        };
    }

    /**
     * A real, disabled LoggerFactory (forChannel() yields a NullLogger) wired
     * with trivial actor/origin resolvers — enough to prove the resolution path
     * without writing log files.
     */
    private function loggerFactory(): LoggerFactory
    {
        $resolver = new class implements ActorResolverInterface, OriginResolverInterface {
            public function resolve(): string
            {
                return 'system';
            }
        };

        return new LoggerFactory(sys_get_temp_dir(), $resolver, $resolver, false);
    }

    /**
     * In-memory PSR-3 spy logger recording every record.
     */
    private function spyLogger(): object
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             * @param mixed                $level
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
