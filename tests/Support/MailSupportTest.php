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

use Middag\WordPress\Support\MailSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MailSupport::class)]
final class MailSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_mail'] = [];
        $GLOBALS['__wp_test_filters'] = [];
        unset($GLOBALS['__wp_test_mail_result']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_mail'],
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__wp_test_mail_result'],
        );
    }

    #[Test]
    public function sendDelegatesToWpMailAndReturnsItsResult(): void
    {
        $result = MailSupport::send('to@example.test', 'Subject', '<p>Body</p>', ['Content-Type: text/html']);

        self::assertTrue($result);
        $sent = $GLOBALS['__wp_test_mail'][0] ?? null;
        self::assertNotNull($sent, 'the mail was not sent');
        self::assertSame('to@example.test', $sent['to']);
        self::assertSame('Subject', $sent['subject']);
        self::assertSame(['Content-Type: text/html'], $sent['headers']);
    }

    #[Test]
    public function sendPropagatesAFailureResult(): void
    {
        $GLOBALS['__wp_test_mail_result'] = false;

        self::assertFalse(MailSupport::send('to@example.test', 'S', 'B'));
    }

    #[Test]
    public function setFromRegistersBothFromFilters(): void
    {
        MailSupport::setFrom('noreply@example.test', 'MIDDAG');

        self::assertArrayHasKey('wp_mail_from', $GLOBALS['__wp_test_filters']);
        self::assertArrayHasKey('wp_mail_from_name', $GLOBALS['__wp_test_filters']);

        $fromCallback = $GLOBALS['__wp_test_filters']['wp_mail_from'][0]['callback'];
        $nameCallback = $GLOBALS['__wp_test_filters']['wp_mail_from_name'][0]['callback'];
        self::assertSame('noreply@example.test', $fromCallback());
        self::assertSame('MIDDAG', $nameCallback());
    }
}
