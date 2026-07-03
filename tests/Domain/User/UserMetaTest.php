<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Domain\User;

use Middag\WordPress\Domain\User\UserMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserMeta::class)]
final class UserMetaTest extends TestCase
{
    private UserMeta $meta;

    protected function setUp(): void
    {
        $this->meta = new UserMeta();
        $GLOBALS['__wp_test_metadata'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_metadata']);
    }

    #[Test]
    public function getReturnsTheStoredValue(): void
    {
        $this->meta->set(7, 'nickname', 'ana');

        self::assertSame('ana', $this->meta->get(7, 'nickname'));
    }

    #[Test]
    public function getFallsBackToTheDefaultWhenUnset(): void
    {
        self::assertSame('anon', $this->meta->get(7, 'nickname', 'anon'));
        self::assertNull($this->meta->get(7, 'nickname'));
    }

    #[Test]
    public function getAllFiltersInternalKeysAndUnserializesValues(): void
    {
        $this->meta->set(7, 'plain', 'v1');
        $this->meta->set(7, 'ser', serialize(['a' => 1]));
        $this->meta->set(7, 'wp_capabilities', 'internal');
        $this->meta->set(7, '_hidden', 'internal');

        self::assertSame(
            ['plain' => 'v1', 'ser' => ['a' => 1]],
            $this->meta->getAll(7),
        );
    }

    #[Test]
    public function setBatchWritesValuesAndDeletesNulls(): void
    {
        $this->meta->set(7, 'stale', 'old');

        $this->meta->setBatch(7, [
            'stale' => null,
            'fresh' => 'new',
        ]);

        self::assertFalse($this->meta->has(7, 'stale'));
        self::assertSame('new', $this->meta->get(7, 'fresh'));
    }

    #[Test]
    public function deleteRemovesTheKey(): void
    {
        $this->meta->set(7, 'nickname', 'ana');

        $this->meta->delete(7, 'nickname');

        self::assertFalse($this->meta->has(7, 'nickname'));
    }

    #[Test]
    public function hasReportsKeyExistence(): void
    {
        self::assertFalse($this->meta->has(7, 'nickname'));

        $this->meta->set(7, 'nickname', 'ana');

        self::assertTrue($this->meta->has(7, 'nickname'));
    }
}
