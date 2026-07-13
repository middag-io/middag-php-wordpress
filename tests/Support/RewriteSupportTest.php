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

use Middag\WordPress\Support\RewriteSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RewriteSupport::class)]
final class RewriteSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_rewrite_rules'] = [];
        $GLOBALS['__wp_test_flush_rewrite'] = [];
        $GLOBALS['__wp_test_query_vars'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_rewrite_rules'],
            $GLOBALS['__wp_test_flush_rewrite'],
            $GLOBALS['__wp_test_query_vars'],
        );
    }

    #[Test]
    public function addRuleRegistersWithTopPlacementByDefault(): void
    {
        RewriteSupport::addRule('^acme/(\d+)/?$', 'index.php?acme_route=show', 'top');

        self::assertSame(
            [['regex' => '^acme/(\d+)/?$', 'query' => 'index.php?acme_route=show', 'after' => 'top']],
            $GLOBALS['__wp_test_rewrite_rules'],
        );
    }

    #[Test]
    public function addRuleHonoursBottomPlacement(): void
    {
        RewriteSupport::addRule('^acme/?$', 'index.php?acme_route=index', 'bottom');

        self::assertSame('bottom', $GLOBALS['__wp_test_rewrite_rules'][0]['after']);
    }

    #[Test]
    public function flushRecordsAHardFlushByDefault(): void
    {
        RewriteSupport::flush();

        self::assertSame([true], $GLOBALS['__wp_test_flush_rewrite']);
    }

    #[Test]
    public function flushRecordsASoftFlushWhenRequested(): void
    {
        RewriteSupport::flush(false);

        self::assertSame([false], $GLOBALS['__wp_test_flush_rewrite']);
    }

    #[Test]
    public function queryVarReadsARegisteredValue(): void
    {
        $GLOBALS['__wp_test_query_vars']['acme_route'] = 'show';

        self::assertSame('show', RewriteSupport::queryVar('acme_route'));
    }

    #[Test]
    public function queryVarReturnsDefaultWhenAbsent(): void
    {
        self::assertSame('', RewriteSupport::queryVar('acme_route'));
        self::assertSame('fallback', RewriteSupport::queryVar('missing', 'fallback'));
    }
}
