<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Database;

use Middag\WordPress\Database\WpdbSqlDialect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpdbSqlDialect::class)]
final class WpdbSqlDialectTest extends TestCase
{
    #[Test]
    public function tablePrependsThePrefixToLogicalNames(): void
    {
        $dialect = new WpdbSqlDialect('wp_');

        self::assertSame('wp_posts', $dialect->table('posts'));
    }

    #[Test]
    public function tablePassesAlreadyPrefixedNamesThrough(): void
    {
        $dialect = new WpdbSqlDialect('wp_');

        self::assertSame('wp_posts', $dialect->table('wp_posts'));
    }

    #[Test]
    public function tableWithoutPrefixReturnsTheNameAsIs(): void
    {
        $dialect = new WpdbSqlDialect();

        self::assertSame('posts', $dialect->table('posts'));
    }

    #[Test]
    public function inClauseEmitsNamedPlaceholdersAndParams(): void
    {
        [$sql, $params] = (new WpdbSqlDialect())->inClause([10, 20, 30]);

        self::assertSame('IN (:p0,:p1,:p2)', $sql);
        self::assertSame(['p0' => 10, 'p1' => 20, 'p2' => 30], $params);
    }

    #[Test]
    public function inClauseHonoursACustomPrefix(): void
    {
        [$sql, $params] = (new WpdbSqlDialect())->inClause(['a'], 'x');

        self::assertSame('IN (:x0)', $sql);
        self::assertSame(['x0' => 'a'], $params);
    }

    #[Test]
    public function emptyInClauseEmitsANeverTruePredicate(): void
    {
        [$sql, $params] = (new WpdbSqlDialect())->inClause([]);

        self::assertSame('IN (NULL)', $sql);
        self::assertSame([], $params);
    }

    #[Test]
    public function compareTextIsAPassthroughOnMysql(): void
    {
        self::assertSame('body', (new WpdbSqlDialect())->compareText('body'));
    }

    #[Test]
    public function limitOffsetCoversAllCombinations(): void
    {
        $dialect = new WpdbSqlDialect();

        self::assertSame('', $dialect->limitOffset(null, null));
        self::assertSame(' LIMIT 5', $dialect->limitOffset(5, null));
        self::assertSame(' LIMIT 5 OFFSET 10', $dialect->limitOffset(5, 10));
        self::assertSame(sprintf(' LIMIT %d OFFSET 10', PHP_INT_MAX), $dialect->limitOffset(null, 10));
        self::assertSame(' LIMIT 0', $dialect->limitOffset(-3, null), 'negative limit is clamped to zero');
        self::assertSame(' LIMIT 5 OFFSET 0', $dialect->limitOffset(5, -1), 'negative offset is clamped to zero');
    }

    #[Test]
    public function lockClauseMapsKnownModesAndIgnoresOthers(): void
    {
        $dialect = new WpdbSqlDialect();

        self::assertSame(' FOR UPDATE', $dialect->lockClause('update'));
        self::assertSame(' FOR SHARE', $dialect->lockClause('share'));
        self::assertSame('', $dialect->lockClause('nope'));
    }

    #[Test]
    public function upsertClauseOverwritesListedColumnsWithInsertedValues(): void
    {
        $clause = (new WpdbSqlDialect())->upsertClause(['slug'], ['title', 'body']);

        self::assertSame(' ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)', $clause);
    }

    #[Test]
    public function upsertClauseWithNoUpdateColumnsEmitsANoOpAssignment(): void
    {
        $dialect = new WpdbSqlDialect();

        self::assertSame(' ON DUPLICATE KEY UPDATE slug = slug', $dialect->upsertClause(['slug'], []));
        self::assertSame(' ON DUPLICATE KEY UPDATE id = id', $dialect->upsertClause([], []), 'falls back to id');
    }
}
