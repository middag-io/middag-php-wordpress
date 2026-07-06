<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Domain\Post;

use Middag\WordPress\Domain\Post\PostMetaRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural coverage for PostMetaRepository.
 *
 * The repository delegates to the WordPress post-meta wrappers get_post_meta(),
 * update_post_meta(), delete_post_meta() and the cache primer
 * update_postmeta_cache(). Those four are stubbed in tests/stubs/wp-stubs.php,
 * backed by the same $__wp_test_metadata['post'] map as the generic Metadata
 * API stubs, so seeding that global and reading it back exercises the real
 * read/write/delete paths without a WordPress runtime.
 *
 * The only line not exercised is the `!is_array($raw)` guard in getAllForPost():
 * that is defensive against a hostile get_post_meta() return in real WordPress;
 * the stub always returns an array, so the guard cannot fire here.
 *
 * @internal
 */
#[CoversClass(PostMetaRepository::class)]
final class PostMetaRepositoryTest extends TestCase
{
    private PostMetaRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new PostMetaRepository();
        $GLOBALS['__wp_test_metadata'] = ['post' => []];
        $GLOBALS['__wp_test_primed_postmeta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_metadata'], $GLOBALS['__wp_test_primed_postmeta']);
    }

    // -------------------------------------------------------------------------
    // getAllForPost()
    // -------------------------------------------------------------------------

    #[Test]
    public function getAllForPostReturnsPublicMetaAndSkipsInternalKeys(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7] = [
            'color' => 'red',
            'size' => 'large',
            '_edit_lock' => '1700000000:1',
        ];

        $result = $this->repo->getAllForPost(7);

        self::assertSame(['color' => 'red', 'size' => 'large'], $result);
        self::assertArrayNotHasKey('_edit_lock', $result);
    }

    #[Test]
    public function getAllForPostUnserializesStoredValues(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7] = [
            'prefs' => serialize(['theme' => 'dark', 'density' => 3]),
        ];

        $result = $this->repo->getAllForPost(7);

        self::assertSame(['prefs' => ['theme' => 'dark', 'density' => 3]], $result);
    }

    #[Test]
    public function getAllForPostReturnsEmptyArrayWhenPostHasNoMeta(): void
    {
        self::assertSame([], $this->repo->getAllForPost(7));
    }

    #[Test]
    public function getAllForPostPrimesTheMetaCache(): void
    {
        $this->repo->getAllForPost(7);

        self::assertSame([[7]], $GLOBALS['__wp_test_primed_postmeta']);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function getReturnsStoredValue(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7]['color'] = 'blue';

        self::assertSame('blue', $this->repo->get(7, 'color'));
    }

    #[Test]
    public function getReturnsProvidedDefaultWhenKeyMissing(): void
    {
        self::assertSame('fallback', $this->repo->get(7, 'missing', 'fallback'));
    }

    #[Test]
    public function getReturnsNullWhenKeyMissingAndNoDefaultGiven(): void
    {
        self::assertNull($this->repo->get(7, 'missing'));
    }

    // -------------------------------------------------------------------------
    // set() / setBatch()
    // -------------------------------------------------------------------------

    #[Test]
    public function setWritesTheValue(): void
    {
        $this->repo->set(7, 'color', 'green');

        self::assertSame('green', $GLOBALS['__wp_test_metadata']['post'][7]['color']);
    }

    #[Test]
    public function setBatchWritesNonNullValuesAndDeletesNullOnes(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7]['stale'] = 'old';

        $this->repo->setBatch(7, [
            'color' => 'green',
            'size' => 'small',
            'stale' => null,
        ]);

        self::assertSame('green', $GLOBALS['__wp_test_metadata']['post'][7]['color']);
        self::assertSame('small', $GLOBALS['__wp_test_metadata']['post'][7]['size']);
        self::assertArrayNotHasKey('stale', $GLOBALS['__wp_test_metadata']['post'][7]);
    }

    #[Test]
    public function setBatchWithEmptyDataIsNoOp(): void
    {
        $this->repo->setBatch(7, []);

        self::assertSame([], $GLOBALS['__wp_test_metadata']['post']);
    }

    // -------------------------------------------------------------------------
    // delete() / deleteBatch()
    // -------------------------------------------------------------------------

    #[Test]
    public function deleteRemovesTheKey(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7]['color'] = 'red';

        $this->repo->delete(7, 'color');

        self::assertArrayNotHasKey('color', $GLOBALS['__wp_test_metadata']['post'][7]);
    }

    #[Test]
    public function deleteBatchRemovesEachKey(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7] = [
            'color' => 'red',
            'size' => 'large',
            'keep' => 'me',
        ];

        $this->repo->deleteBatch(7, ['color', 'size']);

        self::assertArrayNotHasKey('color', $GLOBALS['__wp_test_metadata']['post'][7]);
        self::assertArrayNotHasKey('size', $GLOBALS['__wp_test_metadata']['post'][7]);
        self::assertSame('me', $GLOBALS['__wp_test_metadata']['post'][7]['keep']);
    }

    #[Test]
    public function deleteBatchWithEmptyKeysIsNoOp(): void
    {
        $GLOBALS['__wp_test_metadata']['post'][7]['color'] = 'red';

        $this->repo->deleteBatch(7, []);

        self::assertSame('red', $GLOBALS['__wp_test_metadata']['post'][7]['color']);
    }

    // -------------------------------------------------------------------------
    // primeCache()
    // -------------------------------------------------------------------------

    #[Test]
    public function primeCacheWithIdsCallsTheCachePrimer(): void
    {
        $this->repo->primeCache([3, 5, 8]);

        self::assertSame([[3, 5, 8]], $GLOBALS['__wp_test_primed_postmeta']);
    }

    #[Test]
    public function primeCacheWithEmptyArrayIsNoOp(): void
    {
        // $postIds === [] → the guard is false, so update_postmeta_cache() is
        // never called.
        $this->repo->primeCache([]);

        self::assertSame([], $GLOBALS['__wp_test_primed_postmeta']);
    }

    // -------------------------------------------------------------------------
    // Class structure / public contract
    // -------------------------------------------------------------------------

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new ReflectionClass(PostMetaRepository::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function exposesExpectedPublicApi(): void
    {
        $reflection = new ReflectionClass(PostMetaRepository::class);
        $methods = array_map(
            static fn (ReflectionMethod $m): string => $m->getName(),
            array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                static fn (ReflectionMethod $m): bool => !$m->isConstructor(),
            ),
        );

        self::assertContains('getAllForPost', $methods);
        self::assertContains('get', $methods);
        self::assertContains('set', $methods);
        self::assertContains('setBatch', $methods);
        self::assertContains('delete', $methods);
        self::assertContains('deleteBatch', $methods);
        self::assertContains('primeCache', $methods);
    }
}
