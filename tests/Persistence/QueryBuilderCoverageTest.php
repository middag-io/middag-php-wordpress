<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Persistence;

use Middag\WordPress\Persistence\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Covers the WP_Query-executing methods of the builder (get/paginate/first/
 * count/ids/find). The chainable builder setters are covered by
 * {@see QueryBuilderTest}; this class drives the terminal operations against
 * the controllable WP_Query / get_post stubs.
 *
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = null;
        $GLOBALS['__wp_test_posts'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_wp_query_result'], $GLOBALS['__wp_test_posts']);
    }

    #[Test]
    public function getReturnsPostsFromTheQuery(): void
    {
        $posts = [$this->post(1), $this->post(2)];
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => $posts];

        self::assertSame($posts, (new QueryBuilder())->get());
    }

    #[Test]
    public function paginateReturnsAStructuredResult(): void
    {
        $posts = [$this->post(1)];
        $GLOBALS['__wp_test_wp_query_result'] = [
            'posts' => $posts,
            'found_posts' => 42,
            'max_num_pages' => 3,
        ];

        $result = (new QueryBuilder())->page(2)->limit(10)->paginate();

        self::assertSame($posts, $result['data']);
        self::assertSame(42, $result['total']);
        self::assertSame(10, $result['per_page']);
        self::assertSame(2, $result['current_page']);
        self::assertSame(3, $result['pages']);
    }

    #[Test]
    public function paginateFallsBackToDefaultPerPageAndPage(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [], 'found_posts' => 0];

        $result = (new QueryBuilder())->paginate();

        self::assertSame(20, $result['per_page']);
        self::assertSame(1, $result['current_page']);
    }

    #[Test]
    public function firstReturnsTheLeadingPost(): void
    {
        $first = $this->post(7);
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [$first, $this->post(8)]];

        self::assertSame($first, (new QueryBuilder())->first());
    }

    #[Test]
    public function firstReturnsNullWhenNoPosts(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => []];

        self::assertNull((new QueryBuilder())->first());
    }

    #[Test]
    public function countReturnsThePostCount(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [], 'post_count' => 5];

        self::assertSame(5, (new QueryBuilder())->count());
    }

    #[Test]
    public function idsReturnsThePosts(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [11, 22, 33]];

        self::assertSame([11, 22, 33], (new QueryBuilder())->ids());
    }

    #[Test]
    public function findReturnsThePostWhenTypeMatches(): void
    {
        $GLOBALS['__wp_test_posts'][7] = $this->post(7, 'book');

        $found = (new QueryBuilder())->postType('book')->find(7);

        self::assertInstanceOf(WP_Post::class, $found);
        self::assertSame(7, $found->ID);
    }

    #[Test]
    public function findReturnsNullWhenTypeDiffers(): void
    {
        $GLOBALS['__wp_test_posts'][7] = $this->post(7, 'page');

        self::assertNull((new QueryBuilder())->postType('book')->find(7));
    }

    #[Test]
    public function findReturnsNullWhenPostMissing(): void
    {
        self::assertNull((new QueryBuilder())->postType('book')->find(999));
    }

    private function post(int $id, string $type = 'post'): WP_Post
    {
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_type = $type;

        return $post;
    }
}
