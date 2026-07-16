<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Persistence\Query;

use InvalidArgumentException;
use Middag\Framework\Persistence\Contract\ConditionCompilerInterface;
use Middag\Framework\Shared\Enum\Operator;
use Middag\WordPress\Database\WpdbSqlDialect;

/**
 * Condition compiler for WordPress' `$wpdb` (MySQL / MariaDB).
 *
 * Translates abstract query conditions (column + operator + value) into
 * `$wpdb`-flavored SQL fragments with named placeholders. Table-agnostic and
 * reusable across repositories; it is the WordPress counterpart of the Moodle
 * adapter's `SqlGenerator` and implements the framework
 * {@see ConditionCompilerInterface} port directly (the port is OSS, so no
 * core-side binding subclass is needed).
 *
 * The few platform idioms it needs — TEXT/CLOB comparison and IN-list assembly —
 * are delegated to the injected {@see WpdbSqlDialect} so the SQL stays MySQL-
 * correct; everything else follows MySQL syntax (`LIKE`, `BETWEEN`, `IS TRUE`).
 *
 * @internal
 */
final readonly class WpdbConditionCompiler implements ConditionCompilerInterface
{
    public function __construct(
        private WpdbSqlDialect $dialect,
    ) {}

    /**
     * Compile a single SQL condition based on the {@see Operator} enum.
     *
     * @param string   $column      SQL column reference (e.g. `item.status`)
     * @param Operator $op          Comparison operator
     * @param mixed    $value       Primary value (may be null for IS / IS NOT NULL)
     * @param mixed    $value2      Secondary value (e.g. BETWEEN upper bound)
     * @param string   $paramPrefix Unique prefix used to name the emitted placeholders
     *
     * @return array{0: string, 1: array<string, mixed>} [SQL fragment, named parameters]
     *
     * @throws InvalidArgumentException when IS / IS NOT receives a non-null, non-bool value
     */
    public function compileCondition(
        string $column,
        Operator $op,
        mixed $value,
        mixed $value2,
        string $paramPrefix
    ): array {
        // Heuristic: detect text columns so the dialect can wrap them for
        // TEXT/CLOB-safe comparison (MySQL: passthrough).
        $isTextColumn = str_contains($column, 'meta_value') || str_ends_with($column, 'description');

        // Exhaustive over Operator — PHPStan flags a missing case at build time,
        // so there is no runtime "unsupported operator" default arm to guard.
        return match ($op) {
            Operator::Eq, Operator::Neq => $this->compileBinary($column, $op, $value, $paramPrefix, $isTextColumn),
            Operator::Gt, Operator::Gte, Operator::Lt, Operator::Lte => $this->compileBinary($column, $op, $value, $paramPrefix, false),
            Operator::Like => $this->compileLike($column, $value, $paramPrefix),
            Operator::In, Operator::NotIn => $this->compileInList($column, $op, $value, $paramPrefix),
            Operator::Between => $this->compileBetween($column, $value, $value2, $paramPrefix),
            Operator::Is, Operator::IsNot => $this->compileNullOrBool($column, $op, $value),
            Operator::Raw => [(string) $value, []],
        };
    }

    /**
     * Equality / relational comparison (EQ, NEQ, GT, GTE, LT, LTE).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileBinary(string $column, Operator $op, mixed $value, string $paramPrefix, bool $isTextColumn): array
    {
        $paramName = $paramPrefix . '_v';
        $columnSql = $isTextColumn ? $this->dialect->compareText($column) : $column;

        return [sprintf('%s %s :%s', $columnSql, $op->value, $paramName), [$paramName => $value]];
    }

    /**
     * Pattern matching (LIKE). Wildcards must already live in the value.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileLike(string $column, mixed $value, string $paramPrefix): array
    {
        $paramName = $paramPrefix . '_v';

        return [sprintf('%s LIKE :%s', $column, $paramName), [$paramName => $value]];
    }

    /**
     * Set membership (IN / NOT IN).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileInList(string $column, Operator $op, mixed $value, string $paramPrefix): array
    {
        if ($value === null || $value === []) {
            // Empty set: IN () matches nothing; NOT IN () matches everything.
            return [$op === Operator::In ? '1=0' : '1=1', []];
        }

        $values = is_array($value) ? array_values($value) : [$value];

        [$inSql, $params] = $this->dialect->inClause($values, $paramPrefix);

        // inClause emits `IN (...)`; negate it for NOT IN.
        $clause = $op === Operator::In ? $inSql : 'NOT ' . $inSql;

        return [sprintf('%s %s', $column, $clause), $params];
    }

    /**
     * Range comparison (BETWEEN min AND max).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileBetween(string $column, mixed $value, mixed $value2, string $paramPrefix): array
    {
        $paramMin = $paramPrefix . '_min';
        $paramMax = $paramPrefix . '_max';

        return [
            sprintf('%s BETWEEN :%s AND :%s', $column, $paramMin, $paramMax),
            [$paramMin => $value, $paramMax => $value2],
        ];
    }

    /**
     * NULL / boolean comparison (IS / IS NOT).
     *
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws InvalidArgumentException when the value is neither null nor boolean
     */
    private function compileNullOrBool(string $column, Operator $op, mixed $value): array
    {
        if ($value === null) {
            return [sprintf('%s %s NULL', $column, $op->value), []];
        }

        if (is_bool($value)) {
            return [sprintf('%s %s %s', $column, $op->value, $value ? 'TRUE' : 'FALSE'), []];
        }

        throw new InvalidArgumentException('IS / IS NOT operator requires a NULL or boolean value.');
    }
}
