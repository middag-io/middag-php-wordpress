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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 */
#[CoversClass(EmailTemplate::class)]
final class EmailTemplateTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/middag_email_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
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
        $path = $this->createTemplate('html.php', '<h1><?= $view["title"] ?></h1>');

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
        $path = $this->createTemplate('multi.php', '<?= $view["name"] ?> - <?= $view["role"] ?>');

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
        $htmlPath = $this->createTemplate('html.php', '<p><?= $view["name"] ?></p>');
        $plainPath = $this->createTemplate('plain.php', 'Hello <?= $view["name"] ?>');

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
    // Scope isolation — render data reaches the template only via $view
    // -------------------------------------------------------------------------

    #[Test]
    public function viewKeysCollidingWithInternalLocalsAreNotDropped(): void
    {
        // Under the old extract($data, EXTR_SKIP), a data key named 'path'
        // collided with renderFile's internal $path local and was silently
        // skipped. With the single $view array there is no extraction and no
        // collision: $view['path'] carries the data verbatim.
        $path = $this->createTemplate('iso.php', '<?= $view["path"] ?>');

        $template = new EmailTemplate($path);
        $result = $template->render(['path' => 'view-wins']);

        self::assertSame('view-wins', $result);
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
        $path = $this->createTemplate('boom.php', '<?php throw new \RuntimeException("kaboom"); ?>');
        $result = (new EmailTemplate($path, null, $spy))->render();

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
