<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Domain;

use Middag\WordPress\Domain\Comment\CommentDto;
use Middag\WordPress\Domain\Media\MediaAttachmentDto;
use Middag\WordPress\Domain\Taxonomy\TaxonomyDto;
use Middag\WordPress\Domain\Taxonomy\TermDto;
use Middag\WordPress\Domain\WooCommerce\OrderReference;
use Middag\WordPress\Domain\WooCommerce\ProductReference;
use Middag\WordPress\Domain\WooCommerce\WooCommerceAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CommentDto::class)]
#[CoversClass(MediaAttachmentDto::class)]
#[CoversClass(TaxonomyDto::class)]
#[CoversClass(TermDto::class)]
#[CoversClass(OrderReference::class)]
#[CoversClass(ProductReference::class)]
#[CoversClass(WooCommerceAvailability::class)]
final class WordPressDomainDtoTest extends TestCase
{
    #[Test]
    public function nativeWordPressDtosExposeStableShapes(): void
    {
        $comment = new CommentDto(7, 42, 'Ada', 'ada@example.test', 'Approved', 'approve');
        $media = new MediaAttachmentDto(8, 'https://example.test/file.pdf', 'application/pdf', 'File', 'Alt');
        $taxonomy = new TaxonomyDto('topic', 'Topic', 'Topics', ['post'], true);
        $term = new TermDto(9, 'topic', 'legal', 'Legal', 1);

        self::assertSame(42, $comment->postId);
        self::assertSame('application/pdf', $media->mimeType);
        self::assertSame(['post'], $taxonomy->objectTypes);
        self::assertSame(1, $term->parentId);
    }

    #[Test]
    public function woocommerceReferencesDoNotRequireWooCommerceRuntime(): void
    {
        $product = new ProductReference(10, 'SKU-10', 'simple');
        $order = new OrderReference(11, '100011', 'processing');

        self::assertSame('SKU-10', $product->sku);
        self::assertSame('processing', $order->status);
        self::assertFalse(WooCommerceAvailability::isAvailable());
    }
}
