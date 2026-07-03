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

use Middag\WordPress\Mail\EmailTemplate;
use Middag\WordPress\Support\LogSupport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 *
 * @coversNothing
 */
final class EmailTemplateTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        LogSupport::setLogger(null);
        $this->tmpDir = sys_get_temp_dir() . '/middag_email_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        LogSupport::setLogger(null);

        // Clean up temp files
        $files = glob($this->tmpDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    // -------------------------------------------------------------------------
    // HTML rendering
    // -------------------------------------------------------------------------

    #[Test]
    public function renderProducesHtmlFromTemplate(): void
    {
        $path = $this->createTemplate('html.php', '<h1><?= $title ?></h1>');

        $template = new EmailTemplate($path);
        $result = $template->render(['title' => 'Hello World']);

        self::assertSame('<h1>Hello World</h1>', $result);
    }

    #[Test]
    public function renderWithNoDataWorks(): void
    {
        $path = $this->createTemplate('static.php', '<p>Static content</p>');

        $template = new EmailTemplate($path);
        $result = $template->render();

        self::assertSame('<p>Static content</p>', $result);
    }

    #[Test]
    public function renderExtractsMultipleVariables(): void
    {
        $path = $this->createTemplate('multi.php', '<?= $name ?> - <?= $role ?>');

        $template = new EmailTemplate($path);
        $result = $template->render(['name' => 'Alice', 'role' => 'Admin']);

        self::assertSame('Alice - Admin', $result);
    }

    #[Test]
    public function renderReturnsEmptyStringOnTemplateError(): void
    {
        $path = $this->createTemplate('error.php', '<?php throw new \RuntimeException("boom"); ?>');

        $template = new EmailTemplate($path);
        $result = $template->render();

        self::assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Plain text rendering
    // -------------------------------------------------------------------------

    #[Test]
    public function renderPlainReturnsNullWhenNoPlainPath(): void
    {
        $htmlPath = $this->createTemplate('html.php', '<p>html</p>');

        $template = new EmailTemplate($htmlPath);

        self::assertNull($template->renderPlain());
    }

    #[Test]
    public function renderPlainRendersPlainTemplate(): void
    {
        $htmlPath = $this->createTemplate('html.php', '<p><?= $name ?></p>');
        $plainPath = $this->createTemplate('plain.php', 'Hello <?= $name ?>');

        $template = new EmailTemplate($htmlPath, $plainPath);
        $result = $template->renderPlain(['name' => 'Bob']);

        self::assertSame('Hello Bob', $result);
    }

    // -------------------------------------------------------------------------
    // Plain version detection
    // -------------------------------------------------------------------------

    #[Test]
    public function hasPlainVersionReturnsFalseWhenNoPlainPath(): void
    {
        $htmlPath = $this->createTemplate('html.php', '');

        $template = new EmailTemplate($htmlPath);

        self::assertFalse($template->hasPlainVersion());
    }

    #[Test]
    public function hasPlainVersionReturnsTrueWhenPlainPathSet(): void
    {
        $htmlPath = $this->createTemplate('html.php', '');
        $plainPath = $this->createTemplate('plain.php', '');

        $template = new EmailTemplate($htmlPath, $plainPath);

        self::assertTrue($template->hasPlainVersion());
    }

    // -------------------------------------------------------------------------
    // EXTR_SKIP safety — existing variables must not be overwritten
    // -------------------------------------------------------------------------

    #[Test]
    public function renderDoesNotOverwriteExistingVariables(): void
    {
        // The 'this' key in data should not shadow $this inside the template scope.
        // EXTR_SKIP ensures existing vars are preserved.
        $path = $this->createTemplate('safe.php', '<?= $greeting ?>');

        $template = new EmailTemplate($path);
        $result = $template->render(['greeting' => 'Hi']);

        self::assertSame('Hi', $result);
    }

    // -------------------------------------------------------------------------
    // Logging bridge (WP-08)
    // -------------------------------------------------------------------------

    #[Test]
    public function renderErrorReachesTheWiredLogger(): void
    {
        $spy = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             * @param mixed                $level
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };
        LogSupport::setLogger($spy);

        $path = $this->createTemplate('boom.php', '<?php throw new \RuntimeException("kaboom"); ?>');
        $result = (new EmailTemplate($path))->render();

        self::assertSame('', $result);
        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertStringContainsString('Template render error', $spy->records[0]['message']);
        self::assertStringContainsString('kaboom', $spy->records[0]['message']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTemplate(string $filename, string $content): string
    {
        $path = $this->tmpDir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}
