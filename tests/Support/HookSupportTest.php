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

use Middag\WordPress\Support\HookSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HookSupport::class)]
final class HookSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_actions'] = [];
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__middag_test_wp_filters'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_actions'],
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__middag_test_wp_filters'],
        );
    }

    #[Test]
    public function addActionDelegatesToWordPressWithPriorityAndArgCount(): void
    {
        $callback = static fn (): null => null;

        HookSupport::addAction('init', $callback, 20, 2);

        $registered = $GLOBALS['__wp_test_actions']['init'][0] ?? null;
        self::assertNotNull($registered, 'the action was not registered');
        self::assertSame($callback, $registered['callback']);
        self::assertSame(20, $registered['priority']);
        self::assertSame(2, $registered['accepted_args']);
    }

    #[Test]
    public function addFilterDelegatesToWordPress(): void
    {
        $callback = static fn (mixed $v): mixed => $v;

        HookSupport::addFilter('the_content', $callback);

        $registered = $GLOBALS['__wp_test_filters']['the_content'][0] ?? null;
        self::assertNotNull($registered, 'the filter was not registered');
        self::assertSame(10, $registered['priority']);
        self::assertSame(1, $registered['accepted_args']);
    }

    #[Test]
    public function applyFiltersRunsRegisteredFilter(): void
    {
        $GLOBALS['__middag_test_wp_filters']['middag_value'] = static fn (int $v): int => $v + 1;

        self::assertSame(42, HookSupport::applyFilters('middag_value', 41));
    }

    #[Test]
    public function applyFiltersReturnsValueUnchangedWhenNoFilterRegistered(): void
    {
        self::assertSame('untouched', HookSupport::applyFilters('absent_hook', 'untouched'));
    }
}
