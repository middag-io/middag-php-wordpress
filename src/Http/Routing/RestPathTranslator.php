<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Routing;

/**
 * Translates a Symfony route path into a WordPress REST route path.
 *
 * `#[Route]` attributes carry Symfony placeholders (`/organizations/{id}`) plus
 * a separate requirements map (`['id' => '\d+']`); WordPress `register_rest_route`
 * wants the constraint inline as a named capture group
 * (`/organizations/(?P<id>\d+)`). A placeholder without an explicit requirement
 * falls back to `[^/]+` — a single path segment.
 *
 * @internal
 */
final class RestPathTranslator
{
    /**
     * @param array<string, string> $requirements placeholder name => regex
     */
    public static function toWordPress(string $path, array $requirements = []): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $matches) use ($requirements): string {
                $name = $matches[1];
                $pattern = $requirements[$name] ?? '[^/]+';

                return '(?P<' . $name . '>' . $pattern . ')';
            },
            $path,
        );
    }
}
