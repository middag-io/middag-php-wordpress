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

use Middag\WordPress\Settings\Enum\FieldType;
use Middag\WordPress\Settings\Field;
use Middag\WordPress\Settings\Section;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Section::class)]
final class SectionTest extends TestCase
{
    #[Test]
    public function exposesEveryConstructorArgumentThroughReadonlyProperties(): void
    {
        $fields = [
            new Field('acme_site_name', 'Site name'),
            new Field('acme_admin_email', 'Admin email', FieldType::Text),
        ];

        $section = new Section('general', 'General', $fields, 'Top-level options.');

        self::assertSame('general', $section->id);
        self::assertSame('General', $section->title);
        self::assertSame($fields, $section->fields);
        self::assertSame('Top-level options.', $section->description);
    }

    #[Test]
    public function descriptionDefaultsToEmptyString(): void
    {
        $section = new Section('advanced', 'Advanced', []);

        self::assertSame('', $section->description);
    }

    #[Test]
    public function acceptsAnEmptyFieldList(): void
    {
        $section = new Section('empty', 'Empty', []);

        self::assertSame([], $section->fields);
    }

    #[Test]
    public function preservesFieldInstancesAndOrder(): void
    {
        $first = new Field('acme_first', 'First');
        $second = new Field('acme_second', 'Second');

        $section = new Section('ordered', 'Ordered', [$first, $second]);

        self::assertCount(2, $section->fields);
        self::assertSame($first, $section->fields[0]);
        self::assertSame($second, $section->fields[1]);
    }
}
