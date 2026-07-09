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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FieldType::class)]
final class FieldTypeTest extends TestCase
{
    #[Test]
    public function rawHtmlDefaultSanitizerStripsDangerousMarkup(): void
    {
        $sanitize = FieldType::RawHtml->defaultSanitizer();

        $result = $sanitize('<p>ok</p><script>alert(1)</script>');

        self::assertStringContainsString('<p>ok</p>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function rawHtmlDefaultSanitizerCoercesNonStringToEmpty(): void
    {
        $sanitize = FieldType::RawHtml->defaultSanitizer();

        self::assertSame('', $sanitize(['not', 'a', 'string']));
    }

    #[Test]
    public function textDefaultSanitizerCleansScalarInput(): void
    {
        $sanitize = FieldType::Text->defaultSanitizer();

        self::assertSame('plain', $sanitize('plain'));
        self::assertSame('', $sanitize(42));
    }
}
