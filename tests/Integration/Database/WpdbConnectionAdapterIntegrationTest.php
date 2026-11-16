<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Database;

use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Database\WpdbSqlDialect;
use Middag\WordPress\Exception\WordPressDatabaseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use wpdb;

/**
 * The wpdb connection adapter and the SQL dialect against a real database.
 *
 * @internal
 */
#[CoversClass(WpdbConnectionAdapter::class)]
#[CoversClass(WpdbSqlDialect::class)]
final class WpdbConnectionAdapterIntegrationTest extends TestCase
{
    private WpdbConnectionAdapter $adapter;

    private string $table;

    protected function setUp(): void
    {
        parent::setUp();

        $wpdb = $GLOBALS['wpdb'];
        self::assertInstanceOf(wpdb::class, $wpdb);

        $this->adapter = new WpdbConnectionAdapter($wpdb);
        $this->table = $this->adapter->dialect()->table('middaglib_it_items');
        $this->adapter->execute(sprintf(
            'CREATE TABLE %s (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(32) NOT NULL,
                label VARCHAR(191) NOT NULL,
                quantity INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY code (code)
            ) ENGINE=InnoDB',
            $this->table,
        ));
    }

    protected function tearDown(): void
    {
        $this->adapter->execute(sprintf('DROP TABLE IF EXISTS %s', $this->table));
        parent::tearDown();
    }

    #[Test]
    public function dialectPrefixesTheTableWithTheSitePrefix(): void
    {
        self::assertSame($GLOBALS['wpdb']->prefix . 'middaglib_it_items', $this->table);
        self::assertSame($this->table, $this->adapter->dialect()->table($this->table));
    }

    #[Test]
    public function crudRoundTripsThroughTheDatabase(): void
    {
        $id = $this->adapter->insert($this->table, ['code' => 'a', 'label' => "O'Reilly", 'quantity' => 3]);

        self::assertGreaterThan(0, $id);
        self::assertSame(
            ['id' => (string) $id, 'code' => 'a', 'label' => "O'Reilly", 'quantity' => '3'],
            $this->adapter->find($this->table, ['id' => $id]),
        );

        $this->adapter->update($this->table, ['id' => $id, 'quantity' => 7]);
        self::assertSame('7', $this->adapter->find($this->table, ['code' => 'a'])['quantity'] ?? null);

        $this->adapter->delete($this->table, ['id' => $id]);
        self::assertNull($this->adapter->find($this->table, ['id' => $id]));
    }

    #[Test]
    public function namedAndPositionalParametersAreBoundByWpdbPrepare(): void
    {
        $this->seed();

        $named = $this->adapter->fetch(
            sprintf('SELECT code FROM %s WHERE label = :label AND quantity = :quantity', $this->table),
            ['label' => 'Beta', 'quantity' => 2],
        );
        $positional = $this->adapter->fetchAll(
            sprintf('SELECT code FROM %s WHERE quantity >= ? AND label <> ? ORDER BY code', $this->table),
            [2, 'Gamma'],
        );

        self::assertSame(['code' => 'b'], $named);
        self::assertSame([['code' => 'b']], $positional);
    }

    #[Test]
    public function inClauseAndLimitOffsetExecute(): void
    {
        $this->seed();
        $dialect = $this->adapter->dialect();

        [$in, $params] = $dialect->inClause(['a', 'c']);
        $rows = $this->adapter->fetchAll(
            sprintf('SELECT code FROM %s WHERE code %s ORDER BY code', $this->table, $in),
            $params,
        );
        self::assertSame([['code' => 'a'], ['code' => 'c']], $rows);

        [$emptyIn, $emptyParams] = $dialect->inClause([]);
        self::assertSame([], $this->adapter->fetchAll(
            sprintf('SELECT code FROM %s WHERE code %s', $this->table, $emptyIn),
            $emptyParams,
        ));

        $page = $this->adapter->fetchAll(
            sprintf('SELECT code FROM %s ORDER BY code%s', $this->table, $dialect->limitOffset(1, 1)),
        );
        self::assertSame([['code' => 'b']], $page);

        $tail = $this->adapter->fetchAll(
            sprintf('SELECT code FROM %s ORDER BY code%s', $this->table, $dialect->limitOffset(null, 2)),
        );
        self::assertSame([['code' => 'c']], $tail);

        self::assertSame(
            ['a', 'b', 'c'],
            array_column(iterator_to_array($this->adapter->cursor(sprintf('SELECT code FROM %s ORDER BY code', $this->table)), false), 'code'),
        );
    }

    #[Test]
    public function upsertClauseUpdatesTheExistingRow(): void
    {
        $this->seed();
        $dialect = $this->adapter->dialect();

        $affected = $this->adapter->execute(
            sprintf('INSERT INTO %s (code, label, quantity) VALUES (:code, :label, :quantity)', $this->table)
            . $dialect->upsertClause(['code'], ['label', 'quantity']),
            ['code' => 'a', 'label' => 'Alpha 2', 'quantity' => 10],
        );
        self::assertSame(2, $affected, 'MySQL reports 2 affected rows when ON DUPLICATE KEY UPDATE changes a row.');

        $this->adapter->execute(
            sprintf('INSERT INTO %s (code, label, quantity) VALUES (:code, :label, :quantity)', $this->table)
            . $dialect->upsertClause(['code'], []),
            ['code' => 'b', 'label' => 'ignored', 'quantity' => 99],
        );

        self::assertSame(
            [['code' => 'a', 'label' => 'Alpha 2', 'quantity' => '10'], ['code' => 'b', 'label' => 'Beta', 'quantity' => '2']],
            $this->adapter->fetchAll(sprintf('SELECT code, label, quantity FROM %s WHERE code IN (?, ?) ORDER BY code', $this->table), ['a', 'b']),
        );
    }

    #[Test]
    public function transactionCommitsAndRollsBackWithLockClauses(): void
    {
        $dialect = $this->adapter->dialect();

        $id = $this->adapter->transaction(function (WpdbConnectionAdapter $connection) use ($dialect): int {
            $id = $connection->insert($this->table, ['code' => 'tx', 'label' => 'Committed']);
            $locked = $connection->fetch(
                sprintf('SELECT label FROM %s WHERE id = :id%s', $this->table, $dialect->lockClause('update')),
                ['id' => $id],
            );
            self::assertSame(['label' => 'Committed'], $locked);

            $shared = $connection->fetch(
                sprintf('SELECT label FROM %s WHERE id = :id%s', $this->table, $dialect->lockClause('share')),
                ['id' => $id],
            );
            self::assertSame(['label' => 'Committed'], $shared);

            return $id;
        });
        self::assertNotNull($this->adapter->find($this->table, ['id' => $id]));

        try {
            $this->adapter->transaction(function (WpdbConnectionAdapter $connection): never {
                $connection->insert($this->table, ['code' => 'rolled', 'label' => 'Rolled back']);

                throw new RuntimeException('abort');
            });
            self::fail('The transaction should rethrow.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('abort', $runtimeException->getMessage());
        }

        self::assertNull($this->adapter->find($this->table, ['code' => 'rolled']));
    }

    #[Test]
    public function aFailingQueryRaisesTheAdapterException(): void
    {
        $GLOBALS['wpdb']->suppress_errors(true);

        try {
            $this->expectException(WordPressDatabaseException::class);
            $this->adapter->execute(sprintf('SELECT missing_column FROM %s', $this->table));
        } finally {
            $GLOBALS['wpdb']->suppress_errors(false);
        }
    }

    private function seed(): void
    {
        foreach ([['a', 'Alpha', 1], ['b', 'Beta', 2], ['c', 'Gamma', 3]] as [$code, $label, $quantity]) {
            $this->adapter->insert($this->table, ['code' => $code, 'label' => $label, 'quantity' => $quantity]);
        }
    }
}
