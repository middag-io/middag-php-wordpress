<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Logging;

use Middag\WordPress\Support\LogSupport;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that writes to the PHP error log — the lowest common
 * denominator available on every WordPress host (surfaces in `debug.log` when
 * `WP_DEBUG_LOG` is on). Bind it to {@see LogSupport}
 * or the container when no richer logger (Monolog, WC_Logger) is wired.
 *
 * Context values are interpolated into `{placeholders}` per PSR-3; leftover
 * context is appended as JSON so no data is silently dropped.
 *
 * @api
 */
final class PhpErrorLogLogger extends AbstractLogger
{
    public function __construct(
        private readonly string $channel = 'middag',
    ) {}

    /**
     * @param mixed[] $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $text = (string) $message;
        $leftover = $context;

        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';

            if (!str_contains($text, $placeholder)) {
                continue;
            }

            $text = str_replace($placeholder, $this->stringify($value), $text);
            unset($leftover[$key]);
        }

        if ($leftover !== []) {
            $encoded = json_encode($leftover, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $text .= ' ' . ($encoded === false ? '[context unserializable]' : $encoded);
        }

        error_log(sprintf('[%s.%s] %s', $this->channel, (string) $level, $text));
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || \is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }
}
