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

use Middag\Framework\Database\Enum\Capability;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Database\WpdbSqlDialect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use wpdb;

/**
 * @internal
 */
#[CoversClass(WpdbConnectionAdapter::class)]
final class WpdbConnectionAdapterTest extends TestCase
{
    private wpdb $wpdb;

    private WpdbConnectionAdapter $adapter;

    protected function setUp(): void
    {
        $this->wpdb = new wpdb();
        $this->adapter = new WpdbConnectionAdapter($this->wpdb);
    }

    #[Test]
    public function supportsReportsTheMysqlCapabilityMatrix(): void
    {
        self::assertTrue($this->adapter->supports(Capability::Transactions));
        self::assertTrue($this->adapter->supports(Capability::JsonWhere));
        self::assertTrue($this->adapter->supports(Capability::Upsert));
        self::assertTrue($this->adapter->supports(Capability::RowLock));
        self::assertFalse($this->adapter->supports(Capability::Streaming));
        self::assertFalse($this->adapter->supports(Capability::Returning));
        self::assertFalse($this->adapter->supports(Capability::SchemaDiff));
    }

    #[Test]
    public function defaultDialectIsBuiltFromTheWpdbPrefix(): void
    {
        $dialect = $this->adapter->dialect();

        self::assertInstanceOf(WpdbSqlDialect::class, $dialect);
        self::assertSame('wp_items', $dialect->table('items'));
    }

    #[Test]
    public function anInjectedDialectIsReturnedAsIs(): void
    {
        $custom = new WpdbSqlDialect('acme_');
        $adapter = new WpdbConnectionAdapter($this->wpdb, $custom);

        self::assertSame($custom, $adapter->dialect());
    }

    #[Test]
    public function executeRewritesNamedParamsToWpdbPlaceholders(): void
    {
        $this->adapter->execute('UPDATE wp_t SET a = :a WHERE id = :id', ['a' => 'x', 'id' => 7]);

        $prepare = $this->wpdb->calls[0];
        self::assertSame('prepare', $prepare['method']);
        self::assertSame('UPDATE wp_t SET a = %s WHERE id = %d', $prepare['args'][0]);

        $query = $this->wpdb->calls[1];
        self::assertSame('query', $query['method']);
        self::assertSame('UPDATE wp_t SET a = x WHERE id = 7', $query['args'][0]);
    }

    #[Test]
    public function executeFailureThrowsWithTheWpdbError(): void
    {
        $failing = new class extends wpdb {
            public function query(string $query): bool
            {
                $this->last_error = 'syntax error';

                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('syntax error');

        (new WpdbConnectionAdapter($failing))->execute('UPDATE wp_t SET a = 1');
    }

    #[Test]
    public function aNamedParamWithNoMatchingTokenLeavesTheSqlUnchanged(): void
    {
        // ":id" has no matching token in the SQL — replaceFirst() returns the
        // haystack unchanged instead of injecting a placeholder for it.
        $this->adapter->execute('UPDATE wp_t SET a = :a', ['a' => 'x', 'id' => 7]);

        $query = $this->wpdb->calls[1];
        self::assertSame('UPDATE wp_t SET a = x', $query['args'][0]);
    }

    #[Test]
    public function fetchRewritesPositionalParamsAndReturnsTheRow(): void
    {
        $this->wpdb->mock_row = ['id' => '5', 'title' => 'hello'];

        $row = $this->adapter->fetch('SELECT * FROM wp_t WHERE id = ? AND score > ?', [5, 1.5]);

        self::assertSame(['id' => '5', 'title' => 'hello'], $row);
        $prepare = $this->wpdb->calls[0];
        self::assertSame('SELECT * FROM wp_t WHERE id = %d AND score > %f', $prepare['args'][0]);
    }

    #[Test]
    public function fetchReturnsNullWhenNoRowMatches(): void
    {
        $this->wpdb->mock_row = null;

        self::assertNull($this->adapter->fetch('SELECT * FROM wp_t WHERE id = ?', [99]));
    }

    #[Test]
    public function fetchAllReturnsAnEmptyArrayWhenWpdbYieldsNothing(): void
    {
        $this->wpdb->mock_results = null;

        self::assertSame([], $this->adapter->fetchAll('SELECT * FROM wp_t'));
    }

    #[Test]
    public function transactionCommitsAndReturnsTheWorkResult(): void
    {
        $result = $this->adapter->transaction(static fn (): string => 'done');

        self::assertSame('done', $result);
        $statements = array_column($this->wpdb->calls, 'args');
        self::assertSame(['START TRANSACTION'], $statements[0]);
        self::assertSame(['COMMIT'], $statements[1]);
    }

    #[Test]
    public function transactionRollsBackAndRethrowsOnFailure(): void
    {
        try {
            $this->adapter->transaction(static function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('expected the work exception to be rethrown');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('boom', $runtimeException->getMessage());
        }

        $statements = array_column($this->wpdb->calls, 'args');
        self::assertSame(['ROLLBACK'], $statements[1]);
    }

    #[Test]
    public function insertReturnsTheWpdbInsertId(): void
    {
        $this->wpdb->insert_id = 42;

        $id = $this->adapter->insert('wp_t', ['title' => 'x']);

        self::assertSame(42, $id);
        self::assertSame(['wp_t', ['title' => 'x'], null], $this->wpdb->calls[0]['args']);
    }

    #[Test]
    public function insertFailureThrowsWithTheWpdbError(): void
    {
        $failing = new class extends wpdb {
            public function insert(string $table, array $data, mixed $format = null): false|int
            {
                $this->last_error = 'duplicate entry';

                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate entry');

        (new WpdbConnectionAdapter($failing))->insert('wp_t', ['title' => 'x']);
    }

    #[Test]
    public function updateRequiresAnIdElement(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"id"');

        $this->adapter->update('wp_t', ['title' => 'x']);
    }

    #[Test]
    public function updateFailureThrowsWithTheWpdbError(): void
    {
        $failing = new class extends wpdb {
            public function update(string $table, array $data, array $where, mixed $format = null, mixed $where_format = null): false|int
            {
                $this->last_error = 'lock timeout';

                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lock timeout');

        (new WpdbConnectionAdapter($failing))->update('wp_t', ['id' => 7, 'title' => 'x']);
    }

    #[Test]
    public function updateSplitsTheIdIntoTheWhereClause(): void
    {
        $this->adapter->update('wp_t', ['id' => 7, 'title' => 'x']);

        self::assertSame(
            ['wp_t', ['title' => 'x'], ['id' => 7], null, null],
            $this->wpdb->calls[0]['args'],
        );
    }

    #[Test]
    public function deleteFailureThrowsWithTheWpdbError(): void
    {
        $failing = new class extends wpdb {
            public function delete(string $table, array $where, mixed $where_format = null): false|int
            {
                $this->last_error = 'lock timeout';

                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lock timeout');

        (new WpdbConnectionAdapter($failing))->delete('wp_t', ['id' => 1]);
    }

    #[Test]
    public function findCompilesAnEqualityWhereClauseWithLimitOne(): void
    {
        $this->wpdb->mock_row = ['id' => '1'];

        $row = $this->adapter->find('wp_t', ['status' => 'publish', 'author' => 3]);

        self::assertSame(['id' => '1'], $row);
        $getRow = $this->wpdb->calls[1];
        self::assertSame('get_row', $getRow['method']);
        self::assertSame('SELECT * FROM wp_t WHERE status = publish AND author = 3 LIMIT 1', $getRow['args'][0]);
    }

    #[Test]
    public function findAllWithoutConditionsSelectsEverything(): void
    {
        $this->wpdb->mock_results = [['id' => '1'], ['id' => '2']];

        $rows = $this->adapter->findAll('wp_t');

        self::assertCount(2, $rows);
        $getResults = $this->wpdb->calls[0];
        self::assertSame('get_results', $getResults['method']);
        self::assertSame('SELECT * FROM wp_t', $getResults['args'][0]);
    }

    #[Test]
    public function cursorBuffersAndYieldsEveryRow(): void
    {
        $this->wpdb->mock_results = [['id' => '1'], ['id' => '2']];

        $rows = iterator_to_array($this->adapter->cursor('SELECT * FROM wp_t'));

        self::assertSame([['id' => '1'], ['id' => '2']], $rows);
    }
}
