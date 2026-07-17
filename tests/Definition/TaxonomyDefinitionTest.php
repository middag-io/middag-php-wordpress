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

use Middag\WordPress\Definition\TaxonomyDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TaxonomyDefinition::class)]
final class TaxonomyDefinitionTest extends TestCase
{
    #[Test]
    public function constructorExposesSlugObjectTypesAndLabels(): void
    {
        $definition = new TaxonomyDefinition('acme_kind', ['acme_site', 'acme_event'], 'Kind', 'Kinds');

        self::assertSame('acme_kind', $definition->slug);
        self::assertSame(['acme_site', 'acme_event'], $definition->objectTypes);
        self::assertSame('Kind', $definition->singular);
        self::assertSame('Kinds', $definition->plural);
        self::assertSame([], $definition->args);
    }

    #[Test]
    public function toArgsDerivesLabelsAndSensibleDefaults(): void
    {
        $definition = new TaxonomyDefinition('acme_kind', ['acme_site'], 'Kind', 'Kinds');

        $args = $definition->toArgs();

        self::assertSame(['name' => 'Kinds', 'singular_name' => 'Kind'], $args['labels']);
        self::assertFalse($args['public']);
        self::assertTrue($args['show_ui']);
        self::assertFalse($args['hierarchical']);
    }

    #[Test]
    public function toArgsOverridesReplaceOnlyTheProvidedTopLevelKeys(): void
    {
        $definition = new TaxonomyDefinition('acme_kind', ['acme_site'], 'Kind', 'Kinds', args: [
            'hierarchical' => true,
            'public' => true,
        ]);

        $args = $definition->toArgs();

        self::assertTrue($args['hierarchical'], 'override wins over the derived default');
        self::assertTrue($args['public']);
        self::assertTrue($args['show_ui'], 'untouched defaults survive the merge');
        self::assertSame('Kinds', $args['labels']['name'], 'derived labels survive when not overridden');
    }

    #[Test]
    public function objectTypesCanBindToMultiplePostTypes(): void
    {
        $definition = new TaxonomyDefinition('acme_kind', ['acme_site', 'acme_event', 'post'], 'Kind', 'Kinds');

        self::assertSame(['acme_site', 'acme_event', 'post'], $definition->objectTypes);
    }
}
