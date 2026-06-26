<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Email;

use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Support\LogSupport;
use Middag\WordPress\Support\MailSupport;
use Middag\WordPress\Support\PathSupport;

final class EmailSender
{
    private const DEFAULT_CONTENT_TYPE = 'text/html';

    /**
     * Send an email using a template.
     */
    public function send(
        string $to,
        string $subject,
        string $templateName,
        array $data = [],
        array $headers = [],
        array $attachments = [],
    ): bool {
        $template = $this->resolveTemplate($templateName);

        if (!$template instanceof EmailTemplate) {
            LogSupport::error('[MIDDAG Email] Template not found: ' . $templateName);

            return false;
        }

        $html = $template->render($data);

        $defaultHeaders = [
            'Content-Type: ' . self::DEFAULT_CONTENT_TYPE . '; charset=UTF-8',
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        return MailSupport::send($to, $subject, $html, $mergedHeaders, $attachments);
    }

    /**
     * Send a simple email without template (plain text or raw HTML).
     */
    public function sendRaw(
        string $to,
        string $subject,
        string $body,
        bool $isHtml = true,
        array $headers = [],
        array $attachments = [],
    ): bool {
        $contentType = $isHtml ? 'text/html' : 'text/plain';

        $defaultHeaders = [
            sprintf('Content-Type: %s; charset=UTF-8', $contentType),
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        return MailSupport::send($to, $subject, $body, $mergedHeaders, $attachments);
    }

    /**
     * Send email to multiple recipients.
     */
    public function sendBulk(
        array $recipients,
        string $subject,
        string $templateName,
        array $data = [],
        array $headers = [],
    ): array {
        $results = [];

        foreach ($recipients as $email) {
            $results[$email] = $this->send($email, $subject, $templateName, $data, $headers);
        }

        return $results;
    }

    /**
     * Set the "From" name and email for the next send via WP filters.
     * Call before send(). Filters are removed after send completes.
     */
    public function withFrom(string $email, string $name): self
    {
        MailSupport::setFrom($email, $name);

        return $this;
    }

    private function resolveTemplate(string $name): ?EmailTemplate
    {
        // Plugin path has priority; fall back to theme
        foreach ($this->getTemplateCandidatePaths($name) as [$htmlPath, $plainPath]) {
            if (file_exists($htmlPath)) {
                $plainExists = $plainPath !== null && file_exists($plainPath);

                return new EmailTemplate($htmlPath, $plainExists ? $plainPath : null);
            }
        }

        return null;
    }

    /**
     * Returns ordered list of [htmlPath, plainPath|null] pairs to check.
     * Plugin directory is checked first, then theme fallback.
     *
     * @return list<array{0: string, 1: null|string}>
     */
    private function getTemplateCandidatePaths(string $name): array
    {
        $candidates = [];

        // 1. Host-provided base path (templates/emails/), when the host exposes one.
        $basePath = HostContext::get()?->basePath();
        if ($basePath !== null && $basePath !== '') {
            $pluginBase = rtrim($basePath, '/\\') . '/templates/emails/';
            $candidates[] = [
                $pluginBase . $name . '.php',
                $pluginBase . 'plain/' . $name . '.php',
            ];
        }

        // 2. Theme fallback
        $themeBase = PathSupport::stylesheetDirectory() . '/templates/emails/';
        $candidates[] = [
            $themeBase . $name . '.php',
            $themeBase . 'plain/' . $name . '.php',
        ];

        return $candidates;
    }
}
