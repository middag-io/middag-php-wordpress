<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Logging;

use Middag\WordPress\Logging\ErrorLogLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ErrorLogLogger::class)]
final class ErrorLogLoggerTest extends TestCase
{
    private string $logFile;

    private string $previousDestination;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'middag-errorlog-');
        $this->previousDestination = (string) ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousDestination);
        @unlink($this->logFile);
    }

    #[Test]
    public function interpolatesContextAndAppendsLeftoverAsJson(): void
    {
        (new ErrorLogLogger('adapter'))->warning('user {id} failed', ['id' => 42, 'ip' => '127.0.0.1']);

        $line = (string) file_get_contents($this->logFile);
        self::assertStringContainsString('[adapter.warning] user 42 failed {"ip":"127.0.0.1"}', $line);
    }

    #[Test]
    public function defaultChannelAndLevelPrefixTheLine(): void
    {
        (new ErrorLogLogger())->error('boom');

        self::assertStringContainsString('[middag.error] boom', (string) file_get_contents($this->logFile));
    }

    #[Test]
    public function nonScalarInterpolationFallsBackToJson(): void
    {
        (new ErrorLogLogger())->info('payload {data}', ['data' => ['a' => 1]]);

        self::assertStringContainsString('payload {"a":1}', (string) file_get_contents($this->logFile));
    }
}
