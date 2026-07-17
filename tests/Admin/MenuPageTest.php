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

use Middag\WordPress\Admin\MenuPage;
use Middag\WordPress\Security\Enum\WpCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MenuPage::class)]
final class MenuPageTest extends TestCase
{
    #[Test]
    public function constructorExposesTitlesAndAppliesDefaults(): void
    {
        $page = new MenuPage('Acme', 'Acme Menu');

        self::assertSame('Acme', $page->pageTitle);
        self::assertSame('Acme Menu', $page->menuTitle);
        self::assertSame('manage_options', $page->capability, 'default capability');
        self::assertSame('', $page->icon);
        self::assertNull($page->position);
        self::assertSame('/', $page->routeBase);
    }

    #[Test]
    public function capabilityAcceptsARawString(): void
    {
        $page = new MenuPage('Acme', 'Acme Menu', capability: 'edit_posts');

        self::assertSame('edit_posts', $page->capability);
    }

    #[Test]
    public function capabilityNormalizesATypedCapabilityToItsWordPressString(): void
    {
        $page = new MenuPage('Acme', 'Acme Menu', capability: WpCapability::EditPosts);

        self::assertSame('edit_posts', $page->capability);
    }

    #[Test]
    public function constructorExposesIconPositionAndRouteBaseWhenProvided(): void
    {
        $page = new MenuPage(
            'Acme',
            'Acme Menu',
            icon: 'dashicons-admin-generic',
            position: 26,
            routeBase: '/dashboard',
        );

        self::assertSame('dashicons-admin-generic', $page->icon);
        self::assertSame(26, $page->position);
        self::assertSame('/dashboard', $page->routeBase);
    }
}
