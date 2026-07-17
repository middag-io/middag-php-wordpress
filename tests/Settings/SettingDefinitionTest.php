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

use Middag\WordPress\Security\Enum\WpCapability;
use Middag\WordPress\Settings\SettingDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingDefinition::class)]
final class SettingDefinitionTest extends TestCase
{
    #[Test]
    public function constructorExposesGroupNameAndDefaults(): void
    {
        $definition = new SettingDefinition('acme_group', 'acme_option');

        self::assertSame('acme_group', $definition->optionGroup);
        self::assertSame('acme_option', $definition->optionName);
        self::assertSame('', $definition->default);
        self::assertSame('manage_options', $definition->capability, 'default capability');
        self::assertNull($definition->sanitizer);
        self::assertNull($definition->page);
        self::assertSame('default', $definition->section);
        self::assertSame('', $definition->title);
        self::assertNull($definition->render);
    }

    #[Test]
    public function capabilityAcceptsARawString(): void
    {
        $definition = new SettingDefinition('acme_group', 'acme_option', capability: 'edit_posts');

        self::assertSame('edit_posts', $definition->capability);
    }

    #[Test]
    public function capabilityNormalizesATypedCapabilityToItsWordPressString(): void
    {
        $definition = new SettingDefinition('acme_group', 'acme_option', capability: WpCapability::ManageOptions);

        self::assertSame('manage_options', $definition->capability);
    }

    #[Test]
    public function hasFieldIsFalseWithoutAPageOrRenderCallback(): void
    {
        $definition = new SettingDefinition('acme_group', 'acme_option');

        self::assertFalse($definition->hasField());
    }

    #[Test]
    public function hasFieldIsFalseWhenOnlyPageIsSet(): void
    {
        $definition = new SettingDefinition('acme_group', 'acme_option', page: 'acme-settings');

        self::assertFalse($definition->hasField(), 'a page without a callable render is not a field');
    }

    #[Test]
    public function hasFieldIsFalseWhenOnlyRenderIsSet(): void
    {
        $definition = new SettingDefinition(
            'acme_group',
            'acme_option',
            render: static fn (): string => '<input>',
        );

        self::assertFalse($definition->hasField(), 'a callable render without a page is not a field');
    }

    #[Test]
    public function hasFieldIsTrueWhenBothPageAndACallableRenderAreSet(): void
    {
        $definition = new SettingDefinition(
            'acme_group',
            'acme_option',
            page: 'acme-settings',
            render: static fn (): string => '<input>',
        );

        self::assertTrue($definition->hasField());
    }

    #[Test]
    public function hasFieldIsFalseWhenRenderIsSetButNotCallable(): void
    {
        $definition = new SettingDefinition(
            'acme_group',
            'acme_option',
            page: 'acme-settings',
            render: 'not-a-callable',
        );

        self::assertFalse($definition->hasField());
    }

    #[Test]
    public function carriesAnArbitraryDefaultValueAndSanitizer(): void
    {
        $sanitizer = static fn (mixed $value): string => trim((string) $value);

        $definition = new SettingDefinition(
            'acme_group',
            'acme_option',
            default: 'fallback',
            sanitizer: $sanitizer,
            section: 'general',
            title: 'Acme Option',
        );

        self::assertSame('fallback', $definition->default);
        self::assertSame($sanitizer, $definition->sanitizer);
        self::assertSame('general', $definition->section);
        self::assertSame('Acme Option', $definition->title);
    }
}
