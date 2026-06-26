<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Email;

use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Email\EmailSender;
use Middag\WordPress\Support\LogSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 */
#[CoversClass(EmailSender::class)]
final class EmailSenderLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        HostContext::reset();
        LogSupport::setLogger(null);
        // Point the theme fallback at a directory with no email templates.
        $GLOBALS['__middag_test_wp_stylesheet_directory'] = sys_get_temp_dir() . '/middag-no-such-theme';
        $GLOBALS['__wp_test_mail'] = [];
    }

    protected function tearDown(): void
    {
        HostContext::reset();
        LogSupport::setLogger(null);
        unset(
            $GLOBALS['__middag_test_wp_stylesheet_directory'],
            $GLOBALS['__wp_test_mail'],
        );
    }

    #[Test]
    public function templateNotFoundReachesTheWiredLogger(): void
    {
        $spy = $this->spyLogger();
        LogSupport::setLogger($spy);

        $result = (new EmailSender())->send('to@example.test', 'Subject', 'does-not-exist');

        self::assertFalse($result);
        self::assertSame([], $GLOBALS['__wp_test_mail'], 'no mail should be sent when the template is missing');

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertStringContainsString('Template not found', $spy->records[0]['message']);
        self::assertStringContainsString('does-not-exist', $spy->records[0]['message']);
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
