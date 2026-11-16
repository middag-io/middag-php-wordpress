<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

/**
 * Proves the suite runs on the real WordPress tree, of the expected major.
 *
 * @internal
 */
#[CoversNothing]
final class WordPressRuntimeTest extends TestCase
{
    #[Test]
    public function wordPressFunctionsComeFromTheRealTree(): void
    {
        $wordpressDir = realpath((string) getenv('WORDPRESS_DIR'));

        self::assertIsString($wordpressDir);
        self::assertStringStartsWith(
            $wordpressDir . DIRECTORY_SEPARATOR,
            (string) (new ReflectionFunction('add_action'))->getFileName(),
        );
        self::assertTrue(is_blog_installed());
    }

    #[Test]
    public function wordPressIsOfTheRequestedMajor(): void
    {
        $major = getenv('WORDPRESS_MAJOR');
        if (!is_string($major) || $major === '') {
            self::markTestSkipped('WORDPRESS_MAJOR is not set.');
        }

        self::assertMatchesRegularExpression(
            '/^' . preg_quote($major, '/') . '(\.\d+)?$/',
            (string) $GLOBALS['wp_version'],
        );
    }
}
