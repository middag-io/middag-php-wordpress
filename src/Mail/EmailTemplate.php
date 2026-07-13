<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Mail;

use Middag\Framework\Logging\ErrorLogFallbackLogger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @api
 */
final readonly class EmailTemplate
{
    public function __construct(
        private string $htmlPath,
        private ?string $plainPath = null,
        private LoggerInterface $logger = new ErrorLogFallbackLogger(),
    ) {}

    /**
     * Render the HTML template with the given data.
     * Variables are extracted and available in the template file.
     */
    public function render(array $data = []): string
    {
        return $this->renderFile($this->htmlPath, $data);
    }

    /**
     * Render the plain text version (if available).
     */
    public function renderPlain(array $data = []): ?string
    {
        if ($this->plainPath === null) {
            return null;
        }

        return $this->renderFile($this->plainPath, $data);
    }

    /**
     * Check if a plain text version is available.
     */
    public function hasPlainVersion(): bool
    {
        return $this->plainPath !== null;
    }

    private function renderFile(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            include $path;
        } catch (Throwable $throwable) {
            ob_end_clean();
            $this->logger->error(sprintf('[MIDDAG Email] Template render error in %s: %s', $path, $throwable->getMessage()));

            return '';
        }

        return ob_get_clean() ?: '';
    }
}
