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

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use RuntimeException;
use Throwable;
use wpdb;

/**
 * WordPress database adapter — wraps the global `$wpdb` behind the framework
 * {@see ConnectionAdapterInterface} seam.
 *
 * This is the only object in the WordPress adapter that knows about `$wpdb`.
 * Everything above it (query builders, repositories, domain) depends on the
 * framework contract, never on `$wpdb` directly, so the same code runs unchanged
 * on every host.
 *
 * Records and conditions are plain assoc arrays at this boundary; rows come back
 * as assoc arrays (`ARRAY_A`). Mapping rows to domain entities happens above, in
 * repositories.
 *
 * Table names received here are *physical* (already prefixed) — the WordPress
 * dialect is a near-passthrough and does not brace table names, so callers that
 * use the record helpers must pass the prefixed name (see {@see WpdbSqlDialect}).
 *
 * @internal
 */
final readonly class WpdbConnectionAdapter implements ConnectionAdapterInterface
{
    private SqlDialectInterface $dialect;

    public function __construct(
        private wpdb $wpdb,
        ?SqlDialectInterface $dialect = null,
    ) {
        $this->dialect = $dialect ?? new WpdbSqlDialect($wpdb->prefix);
    }

    public function supports(Capability $feature): bool
    {
        return match ($feature) {
            // $wpdb has START TRANSACTION / COMMIT / ROLLBACK; only meaningful on
            // InnoDB. We report true and leave engine choice to the schema layer.
            Capability::TRANSACTIONS => true,
            // No native unbuffered cursor; fetchAll buffers the whole result set.
            Capability::STREAMING => false,
            // MySQL 5.7+/8.0 supports JSON predicates; modern WP baselines do.
            Capability::JSON_WHERE => true,
            // MySQL has no RETURNING.
            Capability::RETURNING => false,
            // INSERT ... ON DUPLICATE KEY UPDATE.
            Capability::UPSERT => true,
            // WordPress owns its own schema lifecycle via dbDelta(); no diffing.
            Capability::SCHEMA_DIFF => false,
            // InnoDB supports SELECT ... FOR UPDATE / FOR SHARE.
            Capability::ROW_LOCK => true,
        };
    }

    public function dialect(): SqlDialectInterface
    {
        return $this->dialect;
    }

    public function execute(string $sql, array $params = []): int
    {
        $result = $this->wpdb->query($this->prepare($sql, $params));

        if ($result === false) {
            throw new RuntimeException(sprintf('wpdb query failed: %s', $this->wpdb->last_error));
        }

        return (int) $result;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        /** @var null|array<string, mixed> $row */
        $row = $this->wpdb->get_row($this->prepare($sql, $params), ARRAY_A);

        return $row ?? null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->wpdb->get_results($this->prepare($sql, $params), ARRAY_A);

        return $rows ?: [];
    }

    public function transaction(callable $work): mixed
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $work($this);
            $this->wpdb->query('COMMIT');

            return $result;
        } catch (Throwable $throwable) {
            $this->wpdb->query('ROLLBACK');

            throw $throwable;
        }
    }

    public function insert(string $table, array $record): int
    {
        $result = $this->wpdb->insert($table, $record);

        if ($result === false) {
            throw new RuntimeException(sprintf('wpdb insert into [%s] failed: %s', $table, $this->wpdb->last_error));
        }

        return $this->wpdb->insert_id;
    }

    public function update(string $table, array $record): void
    {
        if (!isset($record['id'])) {
            throw new RuntimeException(sprintf('update() into [%s] requires an "id" element.', $table));
        }

        $id = $record['id'];
        unset($record['id']);

        $result = $this->wpdb->update($table, $record, ['id' => $id]);

        if ($result === false) {
            throw new RuntimeException(sprintf('wpdb update of [%s] failed: %s', $table, $this->wpdb->last_error));
        }
    }

    public function delete(string $table, array $conditions): void
    {
        $result = $this->wpdb->delete($table, $conditions);

        if ($result === false) {
            throw new RuntimeException(sprintf('wpdb delete from [%s] failed: %s', $table, $this->wpdb->last_error));
        }
    }

    public function find(string $table, array $conditions): ?array
    {
        [$where, $params] = $this->compileConditions($conditions);
        $sql = sprintf('SELECT * FROM %s%s LIMIT 1', $table, $where);

        return $this->fetch($sql, $params);
    }

    public function findAll(string $table, array $conditions = []): array
    {
        [$where, $params] = $this->compileConditions($conditions);
        $sql = sprintf('SELECT * FROM %s%s', $table, $where);

        return $this->fetchAll($sql, $params);
    }

    public function cursor(string $sql, array $params = []): iterable
    {
        // $wpdb has no unbuffered cursor; buffer and yield to satisfy the
        // recordset semantics of the contract. STREAMING capability is false.
        foreach ($this->fetchAll($sql, $params) as $row) {
            yield $row;
        }
    }

    /**
     * Render a $wpdb->prepare()-safe SQL string from positional or named params.
     *
     * Named params (`:name`) are rewritten to `%s`/`%d`/`%f` placeholders in
     * declaration order; positional `?` placeholders are mapped from the list.
     *
     * @param array<int|string, mixed> $params
     */
    private function prepare(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }

        $ordered = [];

        // Named placeholders: replace :key with a printf placeholder in order.
        if ($this->isAssoc($params)) {
            foreach ($params as $name => $value) {
                $token = ':' . ltrim((string) $name, ':');
                $sql = $this->replaceFirst($sql, $token, $this->placeholderFor($value));
                $ordered[] = $value;
            }
        } else {
            foreach ($params as $value) {
                $sql = $this->replaceFirst($sql, '?', $this->placeholderFor($value));
                $ordered[] = $value;
            }
        }

        return $this->wpdb->prepare($sql, ...$ordered);
    }

    private function placeholderFor(mixed $value): string
    {
        return match (true) {
            is_int($value), is_bool($value) => '%d',
            is_float($value) => '%f',
            default => '%s',
        };
    }

    private function replaceFirst(string $haystack, string $needle, string $replace): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }

    /**
     * Compile an equality WHERE clause from a column => value map.
     *
     * @param array<string, mixed> $conditions
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileConditions(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $fragments = [];
        $params = [];
        $i = 0;
        foreach ($conditions as $column => $value) {
            $param = 'w' . $i++;
            $fragments[] = sprintf('%s = :%s', $column, $param);
            $params[$param] = $value;
        }

        return [' WHERE ' . implode(' AND ', $fragments), $params];
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
