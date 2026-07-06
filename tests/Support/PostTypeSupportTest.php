<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\PostTypeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PostTypeSupport::class)]
final class PostTypeSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_post_types'] = [];
        $GLOBALS['__wp_test_taxonomies'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_post_types'],
            $GLOBALS['__wp_test_taxonomies'],
        );
    }

    #[Test]
    public function registerPostTypeDelegatesToWordPressWithArgs(): void
    {
        $args = ['public' => true, 'label' => 'Books'];

        PostTypeSupport::registerPostType('book', $args);

        self::assertArrayHasKey('book', $GLOBALS['__wp_test_post_types']);
        self::assertSame($args, $GLOBALS['__wp_test_post_types']['book']);
    }

    #[Test]
    public function registerPostTypeDefaultsToEmptyArgs(): void
    {
        PostTypeSupport::registerPostType('minimal');

        self::assertArrayHasKey('minimal', $GLOBALS['__wp_test_post_types']);
        self::assertSame([], $GLOBALS['__wp_test_post_types']['minimal']);
    }

    #[Test]
    public function registerTaxonomyDelegatesWithStringObjectType(): void
    {
        $args = ['hierarchical' => true, 'label' => 'Genres'];

        PostTypeSupport::registerTaxonomy('genre', 'book', $args);

        self::assertArrayHasKey('genre', $GLOBALS['__wp_test_taxonomies']);
        self::assertSame('book', $GLOBALS['__wp_test_taxonomies']['genre']['object_type']);
        self::assertSame($args, $GLOBALS['__wp_test_taxonomies']['genre']['args']);
    }

    #[Test]
    public function registerTaxonomyDelegatesWithArrayObjectTypes(): void
    {
        $objectTypes = ['book', 'article'];

        PostTypeSupport::registerTaxonomy('topic', $objectTypes);

        self::assertArrayHasKey('topic', $GLOBALS['__wp_test_taxonomies']);
        self::assertSame($objectTypes, $GLOBALS['__wp_test_taxonomies']['topic']['object_type']);
        self::assertSame([], $GLOBALS['__wp_test_taxonomies']['topic']['args']);
    }
}
