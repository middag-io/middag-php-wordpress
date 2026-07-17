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

use Middag\WordPress\Definition\PostTypeDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PostTypeDefinition::class)]
final class PostTypeDefinitionTest extends TestCase
{
    #[Test]
    public function constructorExposesSlugAndLabels(): void
    {
        $definition = new PostTypeDefinition('acme_site', 'Site', 'Sites');

        self::assertSame('acme_site', $definition->slug);
        self::assertSame('Site', $definition->singular);
        self::assertSame('Sites', $definition->plural);
        self::assertSame([], $definition->args);
    }

    #[Test]
    public function toArgsDerivesLabelsFromSingularAndPlural(): void
    {
        $definition = new PostTypeDefinition('acme_site', 'Site', 'Sites');

        $args = $definition->toArgs();

        self::assertSame([
            'name' => 'Sites',
            'singular_name' => 'Site',
            'add_new_item' => 'Add New Site',
            'edit_item' => 'Edit Site',
            'all_items' => 'All Sites',
            'not_found' => 'No sites found.',
        ], $args['labels']);
    }

    #[Test]
    public function toArgsAppliesSensibleDefaults(): void
    {
        $definition = new PostTypeDefinition('acme_site', 'Site', 'Sites');

        $args = $definition->toArgs();

        self::assertFalse($args['public']);
        self::assertTrue($args['show_ui']);
        self::assertTrue($args['show_in_menu']);
        self::assertSame(['title'], $args['supports']);
    }

    #[Test]
    public function toArgsOverridesReplaceOnlyTheProvidedTopLevelKeys(): void
    {
        $definition = new PostTypeDefinition('acme_site', 'Site', 'Sites', args: [
            'public' => true,
            'supports' => ['title', 'editor'],
        ]);

        $args = $definition->toArgs();

        self::assertTrue($args['public'], 'override wins over the derived default');
        self::assertSame(['title', 'editor'], $args['supports']);
        self::assertTrue($args['show_ui'], 'untouched defaults survive the merge');
        self::assertSame('Sites', $args['labels']['name'], 'derived labels survive when not overridden');
    }

    #[Test]
    public function toArgsOverrideReplacesTheEntireLabelsArrayRatherThanMerging(): void
    {
        // array_replace() only merges at the top level: overriding 'labels'
        // wholesale drops the derived label set instead of merging into it.
        $definition = new PostTypeDefinition('acme_site', 'Site', 'Sites', args: [
            'labels' => ['name' => 'Custom Name'],
        ]);

        $args = $definition->toArgs();

        self::assertSame(['name' => 'Custom Name'], $args['labels']);
    }

    #[Test]
    public function notFoundLabelLowercasesThePluralName(): void
    {
        $definition = new PostTypeDefinition('acme_event', 'Event', 'Events');

        $args = $definition->toArgs();

        self::assertSame('No events found.', $args['labels']['not_found']);
    }
}
