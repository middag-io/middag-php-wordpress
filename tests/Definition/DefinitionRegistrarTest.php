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

use InvalidArgumentException;
use Middag\WordPress\Definition\CronScheduleDefinition;
use Middag\WordPress\Definition\DefinitionRegistrar;
use Middag\WordPress\Definition\PostTypeDefinition;
use Middag\WordPress\Definition\ShortcodeDefinition;
use Middag\WordPress\Definition\TaxonomyDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(DefinitionRegistrar::class)]
#[CoversClass(PostTypeDefinition::class)]
#[CoversClass(TaxonomyDefinition::class)]
#[CoversClass(CronScheduleDefinition::class)]
final class DefinitionRegistrarTest extends TestCase
{
    private DefinitionRegistrar $registrar;

    protected function setUp(): void
    {
        $this->registrar = new DefinitionRegistrar();
        $GLOBALS['__wp_test_post_types'] = [];
        $GLOBALS['__wp_test_taxonomies'] = [];
        $GLOBALS['__wp_test_filters'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_post_types'],
            $GLOBALS['__wp_test_taxonomies'],
            $GLOBALS['__wp_test_filters'],
        );
    }

    #[Test]
    public function postTypeRegistersWithDerivedLabelsAndOverrides(): void
    {
        $this->registrar->register([
            new PostTypeDefinition('acme_site', 'Site', 'Sites', args: ['public' => true]),
        ]);

        $args = $GLOBALS['__wp_test_post_types']['acme_site'];
        self::assertSame('Sites', $args['labels']['name']);
        self::assertSame('Site', $args['labels']['singular_name']);
        self::assertTrue($args['public'], 'override wins over the derived default');
        self::assertSame(['title'], $args['supports']);
    }

    #[Test]
    public function taxonomyRegistersBoundToItsPostTypes(): void
    {
        $this->registrar->register([
            new TaxonomyDefinition('acme_kind', ['acme_site'], 'Kind', 'Kinds'),
        ]);

        $record = $GLOBALS['__wp_test_taxonomies']['acme_kind'];
        self::assertSame(['acme_site'], $record['object_type']);
        self::assertSame('Kinds', $record['args']['labels']['name']);
    }

    #[Test]
    public function cronSchedulesAreExposedThroughTheCronSchedulesFilter(): void
    {
        $this->registrar->register([
            new CronScheduleDefinition('every_five_minutes', 300, 'Every five minutes'),
        ]);

        $filters = $GLOBALS['__wp_test_filters']['cron_schedules'] ?? [];
        self::assertCount(1, $filters);

        $result = $filters[0]['callback'](['hourly' => ['interval' => 3600, 'display' => 'Hourly']]);
        self::assertSame(300, $result['every_five_minutes']['interval']);
        self::assertArrayHasKey('hourly', $result, 'existing schedules are preserved');
    }

    #[Test]
    public function shortcodeRegistersItsRenderCallback(): void
    {
        $GLOBALS['__wp_test_shortcodes'] = [];

        $this->registrar->register([
            new ShortcodeDefinition('acme_portal', static fn (): string => '<div>portal</div>'),
        ]);

        self::assertArrayHasKey('acme_portal', $GLOBALS['__wp_test_shortcodes']);
        self::assertSame('<div>portal</div>', $GLOBALS['__wp_test_shortcodes']['acme_portal']());

        unset($GLOBALS['__wp_test_shortcodes']);
    }

    #[Test]
    public function shortcodeWithNonCallableRenderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registrar->register([
            new ShortcodeDefinition('acme_bad', 'not-a-callable-function-xyz'),
        ]);
    }

    #[Test]
    public function unknownDefinitionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        $this->registrar->register([new stdClass()]);
    }
}
