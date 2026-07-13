<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Security\Enum;

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\WpCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpCapability::class)]
final class WpCapabilityTest extends TestCase
{
    #[Test]
    public function implementsTheCapabilityContract(): void
    {
        self::assertInstanceOf(CapabilityInterface::class, WpCapability::ManageOptions);
    }

    #[Test]
    public function backingValueIsTheExactWordPressCapabilityString(): void
    {
        self::assertSame('manage_options', WpCapability::ManageOptions->value);
        self::assertSame('edit_posts', WpCapability::EditPosts->value);
        self::assertSame('read', WpCapability::Read->value);
        self::assertSame('unfiltered_html', WpCapability::UnfilteredHtml->value);
    }

    #[Test]
    public function toStringMatchesTheBackingValue(): void
    {
        foreach (WpCapability::cases() as $capability) {
            self::assertSame($capability->value, $capability->toString());
        }
    }

    #[Test]
    public function everyBackingValueIsLowercaseSnakeCase(): void
    {
        foreach (WpCapability::cases() as $capability) {
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $capability->value,
                sprintf('%s has a non-snake_case backing value', $capability->name),
            );
        }
    }

    #[Test]
    public function primitiveCapabilitiesAreNotFlaggedAsMeta(): void
    {
        self::assertFalse(WpCapability::ManageOptions->isMeta());
        self::assertFalse(WpCapability::EditPosts->isMeta());
        self::assertFalse(WpCapability::Read->isMeta());
        self::assertFalse(WpCapability::ManageNetwork->isMeta());
    }

    #[Test]
    public function metaCapabilitiesAreFlagged(): void
    {
        // These are checked against an object id and resolved by map_meta_cap;
        // never grant them to a role.
        self::assertTrue(WpCapability::EditPost->isMeta());
        self::assertTrue(WpCapability::ReadPost->isMeta());
        self::assertTrue(WpCapability::DeletePost->isMeta());
        self::assertTrue(WpCapability::EditUser->isMeta());
        self::assertTrue(WpCapability::EditComment->isMeta());
        self::assertTrue(WpCapability::ManagePrivacyOptions->isMeta());
        // Pure map_meta_cap remaps with no object id: granting them is a no-op.
        self::assertTrue(WpCapability::Customize->isMeta());
        self::assertTrue(WpCapability::DeleteSite->isMeta());
    }

    #[Test]
    public function caseValuesAreUnique(): void
    {
        $values = array_map(static fn (WpCapability $c): string => $c->value, WpCapability::cases());

        self::assertSame(array_unique($values), $values);
    }

    #[Test]
    public function deprecatedLegacyCapsAreExcludedFromTheCatalog(): void
    {
        $values = array_map(static fn (WpCapability $c): string => $c->value, WpCapability::cases());

        self::assertNotContains('edit_css', $values);
        self::assertNotContains('edit_files', $values);
        self::assertNotContains('level_0', $values);
        self::assertNotContains('level_10', $values);
    }
}
