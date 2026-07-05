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

/**
 * Tests the fluent builder API by inspecting the WP_Query args array
 * produced by toArgs(). No WordPress runtime required.
 *
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    #[Test]
    public function defaultsProduceSaneArgs(): void
    {
        $args = (new QueryBuilder())->toArgs();

        self::assertSame('post', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame(20, $args['posts_per_page']);
        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
        self::assertSame([], $args['meta_query']);
        self::assertFalse($args['no_found_rows']);
    }

    // -------------------------------------------------------------------------
    // Immutability (clone-on-write)
    // -------------------------------------------------------------------------

    #[Test]
    public function builderIsImmutable(): void
    {
        $original = new QueryBuilder();
        $modified = $original->postType('page');

        self::assertSame('post', $original->toArgs()['post_type']);
        self::assertSame('page', $modified->toArgs()['post_type']);
    }

    // -------------------------------------------------------------------------
    // Post type & status
    // -------------------------------------------------------------------------

    #[Test]
    public function postTypeSetsType(): void
    {
        $args = (new QueryBuilder())->postType('product')->toArgs();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function statusAcceptsString(): void
    {
        $args = (new QueryBuilder())->status('draft')->toArgs();

        self::assertSame('draft', $args['post_status']);
    }

    #[Test]
    public function statusAcceptsArray(): void
    {
        $args = (new QueryBuilder())->status(['publish', 'draft'])->toArgs();

        self::assertSame(['publish', 'draft'], $args['post_status']);
    }

    // -------------------------------------------------------------------------
    // Meta queries
    // -------------------------------------------------------------------------

    #[Test]
    public function metaWhereAddsClause(): void
    {
        $args = (new QueryBuilder())
            ->metaWhere('color', 'red')
            ->toArgs();

        self::assertCount(1, $args['meta_query']);
        self::assertSame([
            'key' => 'color',
            'value' => 'red',
            'compare' => '=',
            'type' => 'CHAR',
        ], $args['meta_query'][0]);
    }

    #[Test]
    public function metaWhereWithCustomCompareAndType(): void
    {
        $args = (new QueryBuilder())
            ->metaWhere('price', 100, '>=', 'NUMERIC')
            ->toArgs();

        self::assertSame('>=', $args['meta_query'][0]['compare']);
        self::assertSame('NUMERIC', $args['meta_query'][0]['type']);
    }

    #[Test]
    public function metaWhereExistsAddsExistsClause(): void
    {
        $args = (new QueryBuilder())
            ->metaWhereExists('featured')
            ->toArgs();

        self::assertSame([
            'key' => 'featured',
            'compare' => 'EXISTS',
        ], $args['meta_query'][0]);
    }

    #[Test]
    public function multipleMetaClausesAccumulate(): void
    {
        $args = (new QueryBuilder())
            ->metaWhere('color', 'red')
            ->metaWhere('size', 'L')
            ->toArgs();

        self::assertCount(2, $args['meta_query']);
    }

    #[Test]
    public function metaRelationSetsRelationKey(): void
    {
        $args = (new QueryBuilder())
            ->metaWhere('a', '1')
            ->metaWhere('b', '2')
            ->metaRelation('OR')
            ->toArgs();

        self::assertSame('OR', $args['meta_query']['relation']);
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    #[Test]
    public function authorSetsAuthorId(): void
    {
        $args = (new QueryBuilder())->author(5)->toArgs();

        self::assertSame(5, $args['author']);
    }

    #[Test]
    public function parentSetsPostParent(): void
    {
        $args = (new QueryBuilder())->parent(10)->toArgs();

        self::assertSame(10, $args['post_parent']);
    }

    #[Test]
    public function searchSetsSearchTerm(): void
    {
        $args = (new QueryBuilder())->search('hello world')->toArgs();

        self::assertSame('hello world', $args['s']);
    }

    #[Test]
    public function whereInSetsPostIn(): void
    {
        $args = (new QueryBuilder())->whereIn([1, 2, 3])->toArgs();

        self::assertSame([1, 2, 3], $args['post__in']);
    }

    // -------------------------------------------------------------------------
    // Ordering
    // -------------------------------------------------------------------------

    #[Test]
    public function orderBySetsFieldAndDirection(): void
    {
        $args = (new QueryBuilder())->orderBy('title', 'asc')->toArgs();

        self::assertSame('title', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByDirectionDefaultsToAsc(): void
    {
        $args = (new QueryBuilder())->orderBy('menu_order')->toArgs();

        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByNormalizesDirectionToUppercase(): void
    {
        $args = (new QueryBuilder())->orderBy('date', 'desc')->toArgs();

        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByMetaCharUsesMetaValue(): void
    {
        $args = (new QueryBuilder())->orderByMeta('sort_name', 'ASC', 'CHAR')->toArgs();

        self::assertSame('sort_name', $args['meta_key']);
        self::assertSame('meta_value', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByMetaNumericUsesMetaValueNum(): void
    {
        $args = (new QueryBuilder())->orderByMeta('price', 'DESC', 'NUMERIC')->toArgs();

        self::assertSame('price', $args['meta_key']);
        self::assertSame('meta_value_num', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    // -------------------------------------------------------------------------
    // Pagination / limits
    // -------------------------------------------------------------------------

    #[Test]
    public function limitSetsPostsPerPage(): void
    {
        $args = (new QueryBuilder())->limit(50)->toArgs();

        self::assertSame(50, $args['posts_per_page']);
    }

    #[Test]
    public function offsetSetsOffset(): void
    {
        $args = (new QueryBuilder())->offset(20)->toArgs();

        self::assertSame(20, $args['offset']);
    }

    #[Test]
    public function pageSetsPaged(): void
    {
        $args = (new QueryBuilder())->page(3)->toArgs();

        self::assertSame(3, $args['paged']);
    }

    // -------------------------------------------------------------------------
    // Found rows toggle
    // -------------------------------------------------------------------------

    #[Test]
    public function noFoundRowsSetsFlagTrue(): void
    {
        $args = (new QueryBuilder())->noFoundRows()->toArgs();

        self::assertTrue($args['no_found_rows']);
    }

    #[Test]
    public function withFoundRowsSetsFlagFalse(): void
    {
        $args = (new QueryBuilder())->noFoundRows()->withFoundRows()->toArgs();

        self::assertFalse($args['no_found_rows']);
    }

    // -------------------------------------------------------------------------
    // Chaining
    // -------------------------------------------------------------------------

    #[Test]
    public function fluentChainBuildsComplexQuery(): void
    {
        $args = (new QueryBuilder())
            ->postType('product')
            ->status('publish')
            ->metaWhere('color', 'red')
            ->metaWhere('price', 50, '<=', 'NUMERIC')
            ->metaRelation('AND')
            ->author(7)
            ->orderBy('title', 'asc')
            ->limit(15)
            ->page(2)
            ->toArgs();

        self::assertSame('product', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame(7, $args['author']);
        self::assertSame('title', $args['orderby']);
        self::assertSame('ASC', $args['order']);
        self::assertSame(15, $args['posts_per_page']);
        self::assertSame(2, $args['paged']);
        self::assertSame('AND', $args['meta_query']['relation']);
        self::assertCount(2, array_filter($args['meta_query'], 'is_array'));
    }
}
