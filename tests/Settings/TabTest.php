<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Settings;

use Middag\WordPress\Settings\Section;
use Middag\WordPress\Settings\Tab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Tab::class)]
final class TabTest extends TestCase
{
    #[Test]
    public function constructorExposesSlugTitleAndSections(): void
    {
        $section = new Section('general', 'General', []);

        $tab = new Tab('advanced', 'Advanced', [$section]);

        self::assertSame('advanced', $tab->slug);
        self::assertSame('Advanced', $tab->title);
        self::assertSame([$section], $tab->sections);
    }

    #[Test]
    public function sectionsCanBeEmpty(): void
    {
        $tab = new Tab('empty', 'Empty', []);

        self::assertSame([], $tab->sections);
    }

    #[Test]
    public function multipleSectionsPreserveOrder(): void
    {
        $first = new Section('first', 'First', []);
        $second = new Section('second', 'Second', []);

        $tab = new Tab('multi', 'Multi', [$first, $second]);

        self::assertCount(2, $tab->sections);
        self::assertSame($first, $tab->sections[0]);
        self::assertSame($second, $tab->sections[1]);
    }
}
