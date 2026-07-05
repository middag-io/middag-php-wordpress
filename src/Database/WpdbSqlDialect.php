<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Database;

use Middag\Framework\Database\Contract\SqlDialectInterface;

/**
 * SQL dialect for WordPress' `$wpdb` (MySQL / MariaDB).
 *
 * `$wpdb` exposes a prefixed, physical table namespace (`wp_`, `wp_2_`, …).
 * Unlike Moodle's `{tablename}` bracing, WordPress raw SQL uses fully-qualified
 * physical names, so {@see table()} prepends the configured prefix and returns
 * the name as-is for raw SQL. Everything else follows MySQL idioms.
 *
 * @internal
 */
final readonly class WpdbSqlDialect implements SqlDialectInterface
{
    public function __construct(
        private string $prefix = '',
    ) {}

    public function table(string $logicalName): string
    {
        // WordPress has no brace syntax — return the physical, prefixed name.
        // Already-prefixed names are passed through unchanged.
        if ($this->prefix !== '' && !str_starts_with($logicalName, $this->prefix)) {
            return $this->prefix . $logicalName;
        }

        return $logicalName;
    }

    public function inClause(array $values, string $prefix = 'p'): array
    {
        if ($values === []) {
            // Empty IN () is invalid SQL; emit a never-true predicate.
            return ['IN (NULL)', []];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $name = $prefix . $i;
            $placeholders[] = ':' . $name;
            $params[$name] = $value;
        }

        return ['IN (' . implode(',', $placeholders) . ')', $params];
    }

    public function compareText(string $column): string
    {
        // MySQL compares TEXT/CLOB columns directly; no wrapper needed.
        return $column;
    }

    public function limitOffset(?int $limit, ?int $offset): string
    {
        if ($limit === null && $offset === null) {
            return '';
        }

        if ($offset === null) {
            return ' LIMIT ' . max(0, (int) $limit);
        }

        // MySQL needs a row-count when OFFSET is present without an explicit
        // LIMIT; use a large max-row sentinel.
        $count = $limit ?? PHP_INT_MAX;

        return sprintf(' LIMIT %d OFFSET %d', $count, max(0, $offset));
    }

    public function lockClause(string $mode): string
    {
        return match ($mode) {
            'update' => ' FOR UPDATE',
            'share' => ' FOR SHARE',
            default => '',
        };
    }

    public function upsertClause(array $uniqueBy, array $update): string
    {
        // MySQL ignores $uniqueBy (it keys off the table's unique/primary index)
        // and overwrites the listed columns with their inserted VALUES().
        if ($update === []) {
            // "DO NOTHING": no-op assignment keyed to the first conflicting col.
            $col = $uniqueBy[0] ?? 'id';

            return sprintf(' ON DUPLICATE KEY UPDATE %s = %s', $col, $col);
        }

        $assignments = array_map(
            static fn (string $column): string => sprintf('%s = VALUES(%s)', $column, $column),
            $update,
        );

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }
}
