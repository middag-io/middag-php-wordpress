<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Settings;

use Middag\WordPress\Support\SanitizeSupport;

/**
 * Closed catalog of admin field controls the declarative settings framework
 * can render. Each case knows its save-time default sanitizer so a plugin gets
 * safe persistence without writing one callback per field.
 *
 * @api
 */
enum FieldType: string
{
    case Text = 'text';

    case Textarea = 'textarea';

    case Number = 'number';

    case Checkbox = 'checkbox';

    case CheckboxList = 'checkbox_list';

    case Select = 'select';

    case Password = 'password';

    case Email = 'email';

    case RawHtml = 'raw_html';

    /**
     * Save-time sanitizer applied when the field declares none.
     */
    public function defaultSanitizer(): callable
    {
        return match ($this) {
            self::Text, self::Password => static fn (mixed $value): string => SanitizeSupport::text(\is_string($value) ? $value : ''),
            self::Textarea => static fn (mixed $value): string => SanitizeSupport::textarea(\is_string($value) ? $value : ''),
            self::Email => static fn (mixed $value): string => SanitizeSupport::email(\is_string($value) ? $value : ''),
            self::Number => static fn (mixed $value): float|int => is_numeric($value) ? $value + 0 : 0,
            self::Checkbox => static fn (mixed $value): string => $value ? '1' : '',
            self::CheckboxList => static fn (mixed $value): array => \is_array($value)
                ? array_map(static fn (mixed $item): string => SanitizeSupport::text(\is_string($item) ? $item : ''), $value)
                : [],
            self::Select => static fn (mixed $value): string => SanitizeSupport::text(\is_string($value) ? $value : ''),
            self::RawHtml => static fn (mixed $value): mixed => $value,
        };
    }
}
