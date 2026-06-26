<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Translation;

use Middag\Framework\Translation\Contract\TranslatorInterface;

/**
 * WordPress translator — bridges the framework {@see TranslatorInterface} to
 * WordPress' gettext layer (`__()` / `_n()`).
 *
 * The `$component` maps to the WordPress text domain; an empty component falls
 * back to a configurable default domain. Pluralisation follows the framework's
 * Symfony-style convention: pass `%count%` in `$params` and either a
 * `singular|plural` pipe message or a single message that WordPress translates
 * via `_n()`. Remaining named params are interpolated into the result.
 */
final readonly class WpTranslator implements TranslatorInterface
{
    public function __construct(
        private string $defaultDomain = 'middag',
    ) {}

    public function get(string $key, string $component = '', array $params = []): string
    {
        $domain = $component !== '' ? $component : $this->defaultDomain;

        $count = $this->extractCount($params);
        $message = $this->translate($key, $domain, $count);

        return $this->interpolate($message, $params);
    }

    public function has(string $key, string $component = ''): bool
    {
        $domain = $component !== '' ? $component : $this->defaultDomain;

        // WordPress has no "key exists" probe; a translation either returns the
        // localised string or the original key. We treat a non-empty result as
        // present. A round-trip equal to the key still counts (untranslated keys
        // are valid messages in the Symfony pattern).
        return $key !== '' && __($key, $domain) !== '';
    }

    /**
     * Translate, choosing the plural form when `%count%` is present and the
     * message carries a `singular|plural` pipe.
     */
    private function translate(string $key, string $domain, ?int $count): string
    {
        if ($count !== null && str_contains($key, '|')) {
            [$singular, $plural] = explode('|', $key, 2);

            return _n($singular, $plural, $count, $domain);
        }

        return __($key, $domain);
    }

    /**
     * Substitute named params into the message. `%count%`-style and Symfony's
     * `%name%` placeholders are both honoured.
     *
     * @param array<string, mixed> $params
     */
    private function interpolate(string $message, array $params): string
    {
        if ($params === []) {
            return $message;
        }

        $replacements = [];
        foreach ($params as $name => $value) {
            $token = '%' . trim((string) $name, '%') . '%';
            $replacements[$token] = $this->stringify($value);
        }

        return strtr($message, $replacements);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function extractCount(array $params): ?int
    {
        foreach (['%count%', 'count'] as $candidate) {
            if (isset($params[$candidate]) && is_numeric($params[$candidate])) {
                return (int) $params[$candidate];
            }
        }

        return null;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
