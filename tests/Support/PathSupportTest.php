<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\PathSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathSupport::class)]
final class PathSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_wp_stylesheet_directory']);
    }

    #[Test]
    public function stylesheetDirectoryDelegatesToWordPress(): void
    {
        $GLOBALS['__middag_test_wp_stylesheet_directory'] = '/var/www/themes/child';

        self::assertSame('/var/www/themes/child', PathSupport::stylesheetDirectory());
    }
}
