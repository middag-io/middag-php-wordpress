<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Persistence\Query;

use InvalidArgumentException;
use Middag\Framework\Shared\Enum\Operator;
use Middag\WordPress\Database\WpdbSqlDialect;
use Middag\WordPress\Persistence\Query\WpdbConditionCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpdbConditionCompiler::class)]
final class WpdbConditionCompilerTest extends TestCase
{
    #[Test]
    public function equalityEmitsANamedPlaceholder(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.status', Operator::Eq, 'active', null, 'w1');

        self::assertSame('item.status = :w1_v', $sql);
        self::assertSame(['w1_v' => 'active'], $params);
    }

    #[Test]
    public function inequalityUsesTheOperatorSql(): void
    {
        [$sql] = $this->compiler()->compileCondition('item.status', Operator::Neq, 'x', null, 'w1');

        self::assertSame('item.status <> :w1_v', $sql);
    }

    #[Test]
    public function relationalOperatorsCompileDirectly(): void
    {
        $compiler = $this->compiler();

        self::assertSame('item.age > :w1_v', $compiler->compileCondition('item.age', Operator::Gt, 18, null, 'w1')[0]);
        self::assertSame('item.age >= :w1_v', $compiler->compileCondition('item.age', Operator::Gte, 18, null, 'w1')[0]);
        self::assertSame('item.age < :w1_v', $compiler->compileCondition('item.age', Operator::Lt, 18, null, 'w1')[0]);
        self::assertSame('item.age <= :w1_v', $compiler->compileCondition('item.age', Operator::Lte, 18, null, 'w1')[0]);
    }

    #[Test]
    public function textColumnsGoThroughTheDialectCompareText(): void
    {
        // MySQL compareText is a passthrough, so the column is unchanged, but the
        // heuristic path (meta_value / *description) is still exercised.
        [$sql, $params] = $this->compiler()->compileCondition('meta1.meta_value', Operator::Eq, 'v', null, 'm1');

        self::assertSame('meta1.meta_value = :m1_v', $sql);
        self::assertSame(['m1_v' => 'v'], $params);

        self::assertSame(
            'item.description <> :w2_v',
            $this->compiler()->compileCondition('item.description', Operator::Neq, 'v', null, 'w2')[0],
        );
    }

    #[Test]
    public function likeEmitsAPlainMysqlLike(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.name', Operator::Like, '%foo%', null, 'w1');

        self::assertSame('item.name LIKE :w1_v', $sql);
        self::assertSame(['w1_v' => '%foo%'], $params);
    }

    #[Test]
    public function inListDelegatesToTheDialect(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.id', Operator::In, [1, 2, 3], null, 'w1');

        self::assertSame('item.id IN (:w10,:w11,:w12)', $sql);
        self::assertSame(['w10' => 1, 'w11' => 2, 'w12' => 3], $params);
    }

    #[Test]
    public function notInPrefixesTheInClause(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.id', Operator::NotIn, [1, 2], null, 'w1');

        self::assertSame('item.id NOT IN (:w10,:w11)', $sql);
        self::assertSame(['w10' => 1, 'w11' => 2], $params);
    }

    #[Test]
    public function scalarInValuesAreWrapped(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.id', Operator::In, 7, null, 'w1');

        self::assertSame('item.id IN (:w10)', $sql);
        self::assertSame(['w10' => 7], $params);
    }

    #[Test]
    public function emptyInMatchesNothingAndEmptyNotInMatchesEverything(): void
    {
        $compiler = $this->compiler();

        self::assertSame('1=0', $compiler->compileCondition('item.id', Operator::In, [], null, 'w1')[0]);
        self::assertSame('1=1', $compiler->compileCondition('item.id', Operator::NotIn, [], null, 'w1')[0]);
        self::assertSame('1=0', $compiler->compileCondition('item.id', Operator::In, null, null, 'w1')[0]);
        self::assertSame([], $compiler->compileCondition('item.id', Operator::In, [], null, 'w1')[1]);
    }

    #[Test]
    public function betweenEmitsMinAndMaxPlaceholders(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('item.price', Operator::Between, 10, 20, 'w1');

        self::assertSame('item.price BETWEEN :w1_min AND :w1_max', $sql);
        self::assertSame(['w1_min' => 10, 'w1_max' => 20], $params);
    }

    #[Test]
    public function isNullAndIsNotNull(): void
    {
        $compiler = $this->compiler();

        self::assertSame('item.deleted IS NULL', $compiler->compileCondition('item.deleted', Operator::Is, null, null, 'w1')[0]);
        self::assertSame('item.deleted IS NOT NULL', $compiler->compileCondition('item.deleted', Operator::IsNot, null, null, 'w1')[0]);
    }

    #[Test]
    public function isBooleanEmitsTrueOrFalseLiterals(): void
    {
        $compiler = $this->compiler();

        self::assertSame('item.active IS TRUE', $compiler->compileCondition('item.active', Operator::Is, true, null, 'w1')[0]);
        self::assertSame('item.active IS NOT FALSE', $compiler->compileCondition('item.active', Operator::IsNot, false, null, 'w1')[0]);
        self::assertSame([], $compiler->compileCondition('item.active', Operator::Is, true, null, 'w1')[1]);
    }

    #[Test]
    public function isWithANonNullNonBoolValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->compiler()->compileCondition('item.active', Operator::Is, 'yes', null, 'w1');
    }

    #[Test]
    public function rawPassesTheValueThroughUnescaped(): void
    {
        [$sql, $params] = $this->compiler()->compileCondition('ignored', Operator::Raw, 'item.a = item.b', null, 'w1');

        self::assertSame('item.a = item.b', $sql);
        self::assertSame([], $params);
    }

    private function compiler(): WpdbConditionCompiler
    {
        return new WpdbConditionCompiler(new WpdbSqlDialect());
    }
}
