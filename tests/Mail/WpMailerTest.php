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

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Mail\Attachment;
use Middag\Framework\Mail\Mail;
use Middag\WordPress\Mail\WpMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpMailer::class)]
final class WpMailerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_mail'] = [];
        unset($GLOBALS['__wp_test_mail_result']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_mail'], $GLOBALS['__wp_test_mail_result']);
    }

    #[Test]
    public function mapsTheFullMailShapeOntoWpMail(): void
    {
        $mail = new Mail(
            to: ['Jane <jane@example.test>', 'john@example.test'],
            subject: 'Hello',
            body: 'plain body',
            htmlBody: '<p>html body</p>',
            from: 'Sender <sender@example.test>',
            replyTo: 'reply@example.test',
            cc: ['cc@example.test'],
            bcc: ['bcc@example.test'],
            attachments: ['/tmp/report.pdf'],
        );

        (new WpMailer())->send($mail);

        self::assertCount(1, $GLOBALS['__wp_test_mail']);
        $call = $GLOBALS['__wp_test_mail'][0];

        self::assertSame(['Jane <jane@example.test>', 'john@example.test'], $call['to']);
        self::assertSame('Hello', $call['subject']);
        self::assertSame('<p>html body</p>', $call['message'], 'htmlBody wins over plain body');
        self::assertContains('From: Sender <sender@example.test>', $call['headers']);
        self::assertContains('Reply-To: reply@example.test', $call['headers']);
        self::assertContains('Cc: cc@example.test', $call['headers']);
        self::assertContains('Bcc: bcc@example.test', $call['headers']);
        self::assertContains('Content-Type: text/html; charset=UTF-8', $call['headers']);
        self::assertSame(['/tmp/report.pdf'], $call['attachments']);
    }

    #[Test]
    public function plainMailStaysPlain(): void
    {
        (new WpMailer())->send(new Mail(to: ['jane@example.test'], subject: 'Hi', body: 'text'));

        $call = $GLOBALS['__wp_test_mail'][0];
        self::assertSame('text', $call['message']);
        self::assertSame([], array_filter((array) $call['headers'], static fn (string $h): bool => str_starts_with($h, 'Content-Type:')));
    }

    #[Test]
    public function transportFailureThrows(): void
    {
        $GLOBALS['__wp_test_mail_result'] = false;

        $this->expectException(MiddagInfrastructureException::class);

        (new WpMailer())->send(new Mail(to: ['jane@example.test'], subject: 'Hi', body: 'text'));
    }

    #[Test]
    public function embeddedAttachmentIsRejectedInsteadOfSilentlyDegrading(): void
    {
        $mail = new Mail(
            to: ['jane@example.test'],
            subject: 'Hi',
            body: 'text',
            attachments: [Attachment::embedded('/tmp/logo.png', 'logo')],
        );

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('cid:logo');

        (new WpMailer())->send($mail);

        self::assertSame([], $GLOBALS['__wp_test_mail'], 'nothing may be sent when an embed is declared');
    }
}
