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

use Middag\WordPress\Cron\CronHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * CronHandler resolves its logger per-run from the dispatch container (via
 * LogSupport::resolve). Tests bind a spy `LoggerInterface` in the container to
 * observe what a cron run reports.
 *
 * @internal
 */
#[CoversClass(CronHandler::class)]
final class CronHandlerLoggingTest extends TestCase
{
    #[Test]
    public function serviceNotFoundReachesTheResolvedLogger(): void
    {
        $spy = $this->spyLogger();

        $callback = CronHandler::dispatch($this->container([LoggerInterface::class => $spy]), 'App\Missing', 'run');
        $callback();

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertStringContainsString('Service not found', $spy->records[0]['message']);
        self::assertStringContainsString('App\Missing', $spy->records[0]['message']);
    }

    #[Test]
    public function serviceThrowingReachesTheResolvedLogger(): void
    {
        $spy = $this->spyLogger();

        $service = new class {
            public function run(): never
            {
                throw new RuntimeException('stripe down');
            }
        };

        $container = $this->container([
            LoggerInterface::class => $spy,
            $service::class => $service,
        ]);
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

        $service = new class {
            public bool $called = false;

            public function run(): void
            {
                $this->called = true;
            }
        };

        $container = $this->container([
            LoggerInterface::class => $spy,
            $service::class => $service,
        ]);
        $callback = CronHandler::dispatch($container, $service::class, 'run');
        $callback();

        self::assertTrue($service->called);
        self::assertSame([], $spy->records);
    }

    #[Test]
    public function dispatchesGracefullyWhenTheContainerWiresNoLogger(): void
    {
        // No LoggerInterface and no LoggerFactory bound: resolve() falls back to
        // the error_log logger, and dispatch must still run the service.
        $service = new class {
            public bool $called = false;

            public function run(): void
            {
                $this->called = true;
            }
        };

        $callback = CronHandler::dispatch($this->container([$service::class => $service]), $service::class, 'run');
        $callback();

        self::assertTrue($service->called);
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
     * In-memory PSR-3 spy logger recording every record.
     */
    private function spyLogger(): LoggerInterface
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
