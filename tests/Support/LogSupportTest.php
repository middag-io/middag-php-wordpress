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
use Middag\Framework\Logging\LoggerFactory;
use Middag\WordPress\Support\LogSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * @internal
 */
#[CoversClass(LogSupport::class)]
final class LogSupportTest extends TestCase
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
    public function setLoggerThenGetLoggerRoundTrips(): void
    {
        self::assertNull(LogSupport::getLogger());

        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        self::assertSame($spy, LogSupport::getLogger());
    }

    #[Test]
    public function errorRoutesToTheWiredLoggerAtErrorLevel(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        LogSupport::error('boom', ['k' => 'v']);

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertSame('boom', $spy->records[0]['message']);
        self::assertSame(['k' => 'v'], $spy->records[0]['context']);
    }

    #[Test]
    public function warningRoutesToTheWiredLoggerAtWarningLevel(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        LogSupport::warning('careful');

        self::assertCount(1, $spy->records);
        self::assertSame('warning', $spy->records[0]['level']);
        self::assertSame('careful', $spy->records[0]['message']);
    }

    #[Test]
    #[DataProvider('levels')]
    public function logForwardsTheGivenLevel(string $level): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        LogSupport::log($level, 'msg');

        self::assertSame($level, $spy->records[0]['level']);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function levels(): iterable
    {
        yield 'error' => ['error'];

        yield 'warning' => ['warning'];

        yield 'info' => ['info'];

        yield 'debug' => ['debug'];
    }

    #[Test]
    public function fallsBackToErrorLogWhenNoLoggerIsWired(): void
    {
        // No logger wired -> the bootstrap fallback path (error_log) is used.
        self::assertNull(LogSupport::getLogger());

        $capture = tempnam(sys_get_temp_dir(), 'middag-logsupport-');
        self::assertIsString($capture);

        $previous = ini_get('error_log');

        try {
            ini_set('error_log', $capture);
            LogSupport::error('bootstrap-failure');
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $written = (string) file_get_contents($capture);
        @unlink($capture);

        self::assertStringContainsString('bootstrap-failure', $written);
    }

    #[Test]
    public function castsStringableMessagesWhenFallingBackToErrorLog(): void
    {
        $message = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable-message';
            }
        };

        $capture = tempnam(sys_get_temp_dir(), 'middag-logsupport-');
        self::assertIsString($capture);

        $previous = ini_get('error_log');

        try {
            ini_set('error_log', $capture);
            LogSupport::error($message);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $written = (string) file_get_contents($capture);
        @unlink($capture);

        self::assertStringContainsString('stringable-message', $written);
    }

    #[Test]
    public function primeFromContainerResolvesTheFrameworkLoggerFactory(): void
    {
        self::assertNull(LogSupport::getLogger());

        $container = $this->container([LoggerFactory::class => $this->loggerFactory()]);

        $primed = LogSupport::primeFromContainer($container);

        self::assertTrue($primed);
        self::assertInstanceOf(LoggerInterface::class, LogSupport::getLogger());
    }

    #[Test]
    public function primeFromContainerReturnsFalseWhenTheContainerHasNoLoggerFactory(): void
    {
        $primed = LogSupport::primeFromContainer($this->container([]));

        self::assertFalse($primed);
        self::assertNull(LogSupport::getLogger());
    }

    #[Test]
    public function primeFromContainerIsIdempotentAndKeepsAnAlreadyWiredLogger(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        // Already wired → returns true without resolving/replacing the logger.
        $primed = LogSupport::primeFromContainer($this->container([LoggerFactory::class => $this->loggerFactory()]));

        self::assertTrue($primed);
        self::assertSame($spy, LogSupport::getLogger());
    }

    #[Test]
    public function primeFromContainerFlowsACustomChannelToTheFileHandler(): void
    {
        self::assertNull(LogSupport::getLogger());

        $base = sys_get_temp_dir() . '/middag-logsupport-channel-' . uniqid();
        $factory = $this->loggerFactory(basePath: $base, enabled: true);

        $primed = LogSupport::primeFromContainer(
            $this->container([LoggerFactory::class => $factory]),
            module: 'clientx',
            channel: 'payments',
        );

        self::assertTrue($primed);

        LogSupport::error('channel-routing-proof');

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
