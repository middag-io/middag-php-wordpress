<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics;

use JsonException;

/**
 * Turns a {@see BootReport} into terminal lines or a JSON document.
 *
 * @internal
 */
final readonly class BootReportRenderer
{
    public const string BOOT_ONLY_NOTICE = 'Scope: boot only. No render check is registered, so a green report does not prove that pages render.';

    public const string BOOT_AND_RENDER_NOTICE = 'Scope: boot and the registered render checks.';

    /**
     * @return list<string>
     */
    public function text(BootReport $report): array
    {
        $lines = [];

        foreach ($report->packages as $package) {
            $version = $package->version === '' ? '(no version)' : $package->version;
            $lines[] = sprintf('%s %s: %s', $package->name, $version, strtoupper($package->status()->value));

            foreach ($package->results as $result) {
                $lines[] = sprintf('  [%s] %s (%s): %s', $result->status->value, $result->name, $result->scope->value, $result->message);
            }
        }

        if ($report->issues !== []) {
            $lines[] = 'Issues:';

            foreach ($report->issues as $issue) {
                $lines[] = '  [fail] ' . $issue;
            }
        }

        $lines[] = $report->coversRender() ? self::BOOT_AND_RENDER_NOTICE : self::BOOT_ONLY_NOTICE;
        $lines[] = sprintf('Status: %s (%d package(s))', strtoupper($report->status()->value), count($report->packages));

        return $lines;
    }

    /**
     * @throws JsonException when the report cannot be encoded
     */
    public function json(BootReport $report): string
    {
        return json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
