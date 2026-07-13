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

use FilesystemIterator;
use Middag\WordPress\Mail\EmailSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Covers the send paths of EmailSender: template resolution + render, raw
 * send, bulk send and the fluent from-setter. The template-not-found path is
 * covered by {@see EmailSenderLoggingTest}.
 *
 * @internal
 */
#[CoversClass(EmailSender::class)]
final class EmailSenderCoverageTest extends TestCase
{
    private string $themeDir;

    protected function setUp(): void
    {
        $this->themeDir = sys_get_temp_dir() . '/middag-wp-email-' . uniqid('', true);
        mkdir($this->themeDir . '/templates/emails/plain', 0o777, true);
        file_put_contents(
            $this->themeDir . '/templates/emails/welcome.php',
            "<?php echo 'Hi ' . (\$view['name'] ?? 'there');",
        );
        file_put_contents(
            $this->themeDir . '/templates/emails/plain/welcome.php',
            "<?php echo 'Hi (plain) ' . (\$view['name'] ?? 'there');",
        );

        $GLOBALS['__middag_test_wp_stylesheet_directory'] = $this->themeDir;
        $GLOBALS['__wp_test_mail'] = [];
        $GLOBALS['__wp_test_mail_result'] = true;
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_wp_stylesheet_directory'],
            $GLOBALS['__wp_test_mail'],
            $GLOBALS['__wp_test_mail_result'],
        );
        $this->deleteDir($this->themeDir);
    }

    #[Test]
    public function sendRendersTheTemplateAndDispatchesMail(): void
    {
        $result = (new EmailSender())->send('to@example.test', 'Welcome', 'welcome', ['name' => 'Ada']);

        self::assertTrue($result);
        self::assertCount(1, $GLOBALS['__wp_test_mail']);

        $mail = $GLOBALS['__wp_test_mail'][0];
        self::assertSame('to@example.test', $mail['to']);
        self::assertSame('Welcome', $mail['subject']);
        self::assertStringContainsString('Hi Ada', $mail['message']);
        self::assertContains('Content-Type: text/html; charset=UTF-8', $mail['headers']);
    }

    #[Test]
    public function sendMergesCustomHeaders(): void
    {
        (new EmailSender())->send('to@example.test', 'S', 'welcome', [], ['X-Custom: 1']);

        $mail = $GLOBALS['__wp_test_mail'][0];
        self::assertContains('Content-Type: text/html; charset=UTF-8', $mail['headers']);
        self::assertContains('X-Custom: 1', $mail['headers']);
    }

    #[Test]
    public function sendRawWithHtmlUsesHtmlContentType(): void
    {
        $result = (new EmailSender())->sendRaw('to@example.test', 'S', '<p>hi</p>', true);

        self::assertTrue($result);
        self::assertContains('Content-Type: text/html; charset=UTF-8', $GLOBALS['__wp_test_mail'][0]['headers']);
    }

    #[Test]
    public function sendRawWithoutHtmlUsesPlainContentType(): void
    {
        (new EmailSender())->sendRaw('to@example.test', 'S', 'hi', false);

        self::assertContains('Content-Type: text/plain; charset=UTF-8', $GLOBALS['__wp_test_mail'][0]['headers']);
    }

    #[Test]
    public function sendBulkDispatchesToEachRecipient(): void
    {
        $results = (new EmailSender())->sendBulk(
            ['a@example.test', 'b@example.test'],
            'Bulk',
            'welcome',
            ['name' => 'Team'],
        );

        self::assertSame(['a@example.test' => true, 'b@example.test' => true], $results);
        self::assertCount(2, $GLOBALS['__wp_test_mail']);
    }

    #[Test]
    public function withFromReturnsTheSenderForChaining(): void
    {
        $sender = new EmailSender();

        self::assertSame($sender, $sender->withFrom('from@example.test', 'From Name'));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
