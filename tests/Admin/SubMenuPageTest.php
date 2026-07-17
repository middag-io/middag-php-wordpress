<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Admin;

use Middag\WordPress\Admin\SubMenuPage;
use Middag\WordPress\Security\Enum\WpCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SubMenuPage::class)]
final class SubMenuPageTest extends TestCase
{
    #[Test]
    public function constructorExposesSlugSuffixAndTitles(): void
    {
        $page = new SubMenuPage('things', 'Things', 'Things Menu');

        self::assertSame('things', $page->slugSuffix);
        self::assertSame('Things', $page->pageTitle);
        self::assertSame('Things Menu', $page->menuTitle);
        self::assertSame('/', $page->routeBase);
    }

    #[Test]
    public function capabilityDefaultsToNullMeaningInheritTheParentMenuPage(): void
    {
        $page = new SubMenuPage('things', 'Things', 'Things Menu');

        self::assertNull($page->capability);
    }

    #[Test]
    public function capabilityAcceptsARawString(): void
    {
        $page = new SubMenuPage('things', 'Things', 'Things Menu', capability: 'edit_posts');

        self::assertSame('edit_posts', $page->capability);
    }

    #[Test]
    public function capabilityNormalizesATypedCapabilityToItsWordPressString(): void
    {
        $page = new SubMenuPage('things', 'Things', 'Things Menu', capability: WpCapability::EditPosts);

        self::assertSame('edit_posts', $page->capability);
    }

    #[Test]
    public function constructorExposesACustomRouteBase(): void
    {
        $page = new SubMenuPage('things', 'Things', 'Things Menu', routeBase: '/things');

        self::assertSame('/things', $page->routeBase);
    }
}
