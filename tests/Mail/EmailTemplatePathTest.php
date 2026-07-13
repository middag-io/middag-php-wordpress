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
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
#[CoversClass(EmailSender::class)]
final class EmailTemplatePathTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_wp_stylesheet_directory'] = '/var/www/themes/active';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_wp_stylesheet_directory']);
    }

    #[Test]
    public function prependsHostBasePathCandidateWhenContextConfigured(): void
    {
        $sender = new EmailSender(context: new WpComponentContext('my-plugin', '1.0.0', '/srv/my-plugin'));

        $candidates = $this->resolveCandidates($sender, 'welcome');

        self::assertCount(2, $candidates);
        self::assertSame('/srv/my-plugin/templates/emails/welcome.php', $candidates[0][0]);
        self::assertSame('/srv/my-plugin/templates/emails/plain/welcome.php', $candidates[0][1]);
        self::assertSame('/var/www/themes/active/templates/emails/welcome.php', $candidates[1][0]);
    }

    #[Test]
    public function fallsBackToThemeOnlyWhenNoBasePathAvailable(): void
    {
        // No host context configured -> base path is null.
        $candidates = $this->resolveCandidates(new EmailSender(), 'welcome');

        self::assertCount(1, $candidates);
        self::assertSame('/var/www/themes/active/templates/emails/welcome.php', $candidates[0][0]);
    }

    /**
     * @return list<array{0: string, 1: null|string}>
     */
    private function resolveCandidates(EmailSender $sender, string $name): array
    {
        $method = new ReflectionMethod(EmailSender::class, 'getTemplateCandidatePaths');

        return $method->invoke($sender, $name);
    }
}
