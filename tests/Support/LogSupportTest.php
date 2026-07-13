<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\ErrorLogFallbackLogger;
use Middag\Framework\Logging\LoggerFactory;
use Middag\WordPress\Support\LogSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * LogSupport is a stateless resolver now (no process-wide logger slot). Each
 * call hands back a logger for a container: an explicitly-bound LoggerInterface
 * first, then the framework channel logger, then the error_log fallback.
 *
 * @internal
 */
#[CoversClass(LogSupport::class)]
final class LogSupportTest extends TestCase
{
    #[Test]
    public function resolvesAnExplicitlyBoundLoggerVerbatim(): void
    {
        $spy = $this->spyLogger();

        $logger = LogSupport::resolve($this->container([LoggerInterface::class => $spy]));

        self::assertSame($spy, $logger);
    }

    #[Test]
    public function resolvesTheChannelLoggerFromTheContainerFactory(): void
    {
        $logger = LogSupport::resolve($this->container([LoggerFactory::class => $this->loggerFactory()]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
        // A disabled factory hands out a NullLogger — the channel path, not the
        // last-resort error_log fallback.
        self::assertNotInstanceOf(ErrorLogFallbackLogger::class, $logger);
    }

    #[Test]
    public function prefersTheExplicitLoggerOverTheFactory(): void
    {
        $spy = $this->spyLogger();

        $logger = LogSupport::resolve($this->container([
            LoggerInterface::class => $spy,
            LoggerFactory::class => $this->loggerFactory(),
        ]));

        self::assertSame($spy, $logger);
    }

    #[Test]
    public function fallsBackToTheErrorLogLoggerWhenNeitherIsWired(): void
    {
        $logger = LogSupport::resolve($this->container([]));

        self::assertInstanceOf(ErrorLogFallbackLogger::class, $logger);
    }

    #[Test]
    public function flowsTheModuleChannelTupleToTheFileHandler(): void
    {
        $base = sys_get_temp_dir() . '/middag-logsupport-channel-' . uniqid();
        $factory = $this->loggerFactory(basePath: $base, enabled: true);

        $logger = LogSupport::resolve(
            $this->container([LoggerFactory::class => $factory]),
            module: 'clientx',
            channel: 'payments',
        );

        $logger->error('channel-routing-proof');

        // The (module, channel) tuple drives the on-disk path of the framework
        // RotatingStreamHandler: {base}/{module}/{channel}/*.log.
        $files = glob($base . '/clientx/payments/*.log') ?: [];
        self::assertNotSame([], $files, 'custom channel did not reach the file handler');

        $written = implode('', array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
        self::assertStringContainsString('channel-routing-proof', $written);

        foreach ($files as $file) {
            @unlink($file);
        }

        @rmdir($base . '/clientx/payments');
        @rmdir($base . '/clientx');
        @rmdir($base);
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
     * A real LoggerFactory wired with trivial actor/origin resolvers. Disabled
     * by default (forChannel() yields a NullLogger — enough to prove the
     * resolution path without writing log files); enable it to exercise the
     * real file handler.
     */
    private function loggerFactory(?string $basePath = null, bool $enabled = false): LoggerFactory
    {
        $resolver = new class implements ActorResolverInterface, OriginResolverInterface {
            public function resolve(): string
            {
                return 'system';
            }
        };

        return new LoggerFactory($basePath ?? sys_get_temp_dir(), $resolver, $resolver, $enabled);
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
