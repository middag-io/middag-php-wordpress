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
     * Render the HTML template with the given data. The data is exposed to the
     * template as a single `$view` array (e.g. `$view['name']`), never as
     * extracted locals.
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
        ob_start();

        try {
            // Isolated render scope: the included template sees ONLY $view (the
            // render data) and the obscured $__templatePath — never $this, the
            // raw $data, or any extracted local. No extract(): template vars are
            // static-analyzable ($view['key']) and a data key that would have
            // collided with a local (e.g. 'path') is no longer silently dropped.
            (static function (string $__templatePath, array $view): void {
                include $__templatePath;
            })($path, $data);
        } catch (Throwable $throwable) {
            ob_end_clean();
            $this->logger->error(sprintf('[MIDDAG Email] Template render error in %s: %s', $path, $throwable->getMessage()));

            return '';
        }

        return ob_get_clean() ?: '';
    }
}
