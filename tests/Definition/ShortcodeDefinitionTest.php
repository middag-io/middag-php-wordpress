<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Definition;

use Middag\WordPress\Definition\ShortcodeDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ShortcodeDefinition::class)]
final class ShortcodeDefinitionTest extends TestCase
{
    #[Test]
    public function constructorExposesTagAndRenderCallback(): void
    {
        $render = static fn (): string => '<div>portal</div>';

        $definition = new ShortcodeDefinition('acme_portal', $render);

        self::assertSame('acme_portal', $definition->tag);
        self::assertSame($render, $definition->render);
    }

    #[Test]
    public function renderCallableReceivesTheStandardShortcodeSignature(): void
    {
        $definition = new ShortcodeDefinition(
            'acme_greet',
            static fn (array|string $atts, ?string $content, string $tag): string => sprintf(
                '<span data-tag="%s" data-name="%s">%s</span>',
                $tag,
                is_array($atts) ? ($atts['name'] ?? '') : '',
                (string) $content,
            ),
        );

        $render = $definition->render;
        self::assertIsCallable($render);

        $markup = $render(['name' => 'Ada'], 'body', 'acme_greet');

        self::assertSame('<span data-tag="acme_greet" data-name="Ada">body</span>', $markup);
    }

    #[Test]
    public function renderAcceptsAnyMixedValueSinceCallabilityIsNotEnforcedHere(): void
    {
        // The value object stores $render as-is (mixed); callability is validated
        // downstream by DefinitionRegistrar, not by this DTO.
        $definition = new ShortcodeDefinition('acme_raw', 'not-a-callable');

        self::assertSame('acme_raw', $definition->tag);
        self::assertSame('not-a-callable', $definition->render);
    }

    #[Test]
    public function renderCanHoldAnArrayCallable(): void
    {
        $callable = [self::class, 'sampleRenderer'];

        $definition = new ShortcodeDefinition('acme_array', $callable);

        self::assertSame($callable, $definition->render);
        self::assertIsCallable($definition->render);
    }

    public static function sampleRenderer(array|string $atts, ?string $content, string $tag): string
    {
        return $tag;
    }
}
