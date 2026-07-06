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

use Middag\WordPress\Domain\Post\PostRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Drives the repository's terminal reads, which delegate to a post-type-scoped
 * QueryBuilder against the controllable WP_Query / get_post stubs.
 *
 * @internal
 */
#[CoversClass(PostRepository::class)]
final class PostRepositoryCoverageTest extends TestCase
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
    public function findResolvesAPostOfTheRepositoryType(): void
    {
        $GLOBALS['__wp_test_posts'][3] = $this->post(3, 'book');

        $found = (new PostRepository('book'))->find(3);

        self::assertInstanceOf(WP_Post::class, $found);
        self::assertSame(3, $found->ID);
    }

    #[Test]
    public function findReturnsNullWhenTypeMismatches(): void
    {
        $GLOBALS['__wp_test_posts'][3] = $this->post(3, 'page');

        self::assertNull((new PostRepository('book'))->find(3));
    }

    #[Test]
    public function firstReturnsTheLeadingPost(): void
    {
        $lead = $this->post(1);
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [$lead]];

        self::assertSame($lead, (new PostRepository('book'))->first());
    }

    #[Test]
    public function getReturnsAllPosts(): void
    {
        $posts = [$this->post(1), $this->post(2)];
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => $posts];

        self::assertSame($posts, (new PostRepository('book'))->get());
    }

    #[Test]
    public function paginateReturnsAStructuredResult(): void
    {
        $posts = [$this->post(1)];
        $GLOBALS['__wp_test_wp_query_result'] = [
            'posts' => $posts,
            'found_posts' => 5,
            'max_num_pages' => 1,
        ];

        $result = (new PostRepository('book'))->paginate(1, 15);

        self::assertSame($posts, $result['data']);
        self::assertSame(5, $result['total']);
        self::assertSame(15, $result['per_page']);
        self::assertSame(1, $result['current_page']);
    }

    #[Test]
    public function countReturnsThePostCount(): void
    {
        $GLOBALS['__wp_test_wp_query_result'] = ['posts' => [], 'post_count' => 9];

        self::assertSame(9, (new PostRepository('book'))->count());
    }

    private function post(int $id, string $type = 'book'): WP_Post
    {
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_type = $type;

        return $post;
    }
}
