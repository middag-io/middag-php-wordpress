<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Mail;

use Middag\WordPress\Mail\EmailSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * @internal
 */
#[CoversClass(EmailSender::class)]
final class EmailSenderLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        // Point the theme fallback at a directory with no email templates.
        $GLOBALS['__middag_test_wp_stylesheet_directory'] = sys_get_temp_dir() . '/middag-no-such-theme';
        $GLOBALS['__wp_test_mail'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_wp_stylesheet_directory'],
            $GLOBALS['__wp_test_mail'],
        );
    }

    #[Test]
    public function templateNotFoundReachesTheInjectedLogger(): void
    {
        $spy = $this->spyLogger();

        $result = (new EmailSender($spy))->send('to@example.test', 'Subject', 'does-not-exist');

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
