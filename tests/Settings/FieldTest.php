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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Field::class)]
final class FieldTest extends TestCase
{
    #[Test]
    public function resolveSanitizerReturnsTheCustomCallableWhenProvided(): void
    {
        $custom = static fn (mixed $value): string => 'sanitized:' . $value;

        $field = new Field(name: 'opt', title: 'Option', sanitizer: $custom);

        $resolved = $field->resolveSanitizer();

        self::assertSame($custom, $resolved);
        self::assertSame('sanitized:x', $resolved('x'));
    }

    #[Test]
    public function resolveSanitizerFallsBackToTheTypeDefaultWhenNull(): void
    {
        $field = new Field(name: 'opt', title: 'Option', type: FieldType::Text);

        self::assertIsCallable($field->resolveSanitizer());
    }

    #[Test]
    public function resolveSanitizerIgnoresANonCallableSanitizerValue(): void
    {
        $field = new Field(name: 'opt', title: 'Option', type: FieldType::Text, sanitizer: 'not-a-callable-fn-xyz');

        self::assertIsCallable($field->resolveSanitizer());
    }
}
