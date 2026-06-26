<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Persistence\Post;

use Middag\WordPress\Persistence\Post\PostRepository;
use Middag\WordPress\Persistence\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests the PostRepository class.
 *
 * Focuses on newQuery() returning a QueryBuilder scoped to the post type.
 * Does NOT test find/first/get/paginate/count (those delegate to QueryBuilder
 * which requires WP_Query runtime and is already tested in QueryBuilderTest).
 *
 * @internal
 *
 * @coversNothing
 */
final class PostRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // newQuery() scoping
    // -------------------------------------------------------------------------

    #[Test]
    public function newQueryReturnsQueryBuilderScopedToPostType(): void
    {
        $repo = new PostRepository('product');
        $builder = $this->callNewQuery($repo);

        self::assertInstanceOf(QueryBuilder::class, $builder);

        $args = $builder->toArgs();
        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function newQueryWithDifferentPostType(): void
    {
        $repo = new PostRepository('invoice');
        $builder = $this->callNewQuery($repo);

        $args = $builder->toArgs();
        self::assertSame('invoice', $args['post_type']);
    }

    #[Test]
    public function newQueryPreservesDefaultBuilderSettings(): void
    {
        $repo = new PostRepository('page');
        $builder = $this->callNewQuery($repo);

        $args = $builder->toArgs();
        self::assertSame('publish', $args['post_status']);
        self::assertSame(20, $args['posts_per_page']);
        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function newQueryReturnsFreshBuilderEachTime(): void
    {
        $repo = new PostRepository('product');

        $builder1 = $this->callNewQuery($repo);
        $builder2 = $this->callNewQuery($repo);

        self::assertNotSame($builder1, $builder2);
    }

    // -------------------------------------------------------------------------
    // Class structure
    // -------------------------------------------------------------------------

    #[Test]
    public function classIsNotFinal(): void
    {
        // PostRepository uses `protected function newQuery()` — designed for extension
        $reflection = new ReflectionClass(PostRepository::class);

        self::assertFalse($reflection->isFinal());
    }

    #[Test]
    public function newQueryIsProtected(): void
    {
        $reflection = new ReflectionClass(PostRepository::class);
        $method = $reflection->getMethod('newQuery');

        self::assertTrue($method->isProtected());
    }

    #[Test]
    public function constructorAcceptsPostTypeString(): void
    {
        $reflection = new ReflectionClass(PostRepository::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        self::assertCount(1, $params);
        self::assertSame('postType', $params[0]->getName());
        self::assertSame('string', $params[0]->getType()->getName());
    }

    // -------------------------------------------------------------------------
    // Public method signatures
    // -------------------------------------------------------------------------

    #[Test]
    public function hasExpectedPublicMethods(): void
    {
        $reflection = new ReflectionClass(PostRepository::class);
        $methods = array_map(
            fn (ReflectionMethod $m): string => $m->getName(),
            array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $m): bool => !$m->isConstructor(),
            ),
        );

        self::assertContains('find', $methods);
        self::assertContains('first', $methods);
        self::assertContains('get', $methods);
        self::assertContains('paginate', $methods);
        self::assertContains('count', $methods);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callNewQuery(PostRepository $repo): QueryBuilder
    {
        $reflection = new ReflectionClass($repo);
        $method = $reflection->getMethod('newQuery');

        return $method->invoke($repo);
    }
}
