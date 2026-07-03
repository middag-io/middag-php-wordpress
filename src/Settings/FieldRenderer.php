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

use Middag\WordPress\Support\EscapeSupport;
use Middag\WordPress\Support\OptionSupport;

/**
 * Renders a {@see Field} as its HTML control, reading the current value from
 * the options table. All dynamic output is escaped; {@see FieldType::RawHtml}
 * is the single deliberate escape hatch (the caller owns that markup).
 *
 * @api
 */
final class FieldRenderer
{
    public function render(Field $field): string
    {
        $value = OptionSupport::get($field->name, $field->default);

        $control = match ($field->type) {
            FieldType::Text, FieldType::Password, FieldType::Email, FieldType::Number => $this->input($field, $value),
            FieldType::Textarea => $this->textarea($field, $value),
            FieldType::Checkbox => $this->checkbox($field, $value),
            FieldType::CheckboxList => $this->checkboxList($field, $value),
            FieldType::Select => $this->select($field, $value),
            FieldType::RawHtml => $field->html,
        };

        if ($field->description !== '') {
            $control .= sprintf('<p class="description">%s</p>', EscapeSupport::html($field->description));
        }

        return $control;
    }

    private function input(Field $field, mixed $value): string
    {
        return sprintf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s>',
            $field->type->value,
            EscapeSupport::attr($field->name),
            EscapeSupport::attr($field->name),
            EscapeSupport::attr($this->scalar($value)),
            $this->attributes($field),
        );
    }

    private function textarea(Field $field, mixed $value): string
    {
        return sprintf(
            '<textarea id="%s" name="%s" rows="5" class="large-text"%s>%s</textarea>',
            EscapeSupport::attr($field->name),
            EscapeSupport::attr($field->name),
            $this->attributes($field),
            EscapeSupport::html($this->scalar($value)),
        );
    }

    private function checkbox(Field $field, mixed $value): string
    {
        return sprintf(
            '<input type="checkbox" id="%s" name="%s" value="1"%s%s>',
            EscapeSupport::attr($field->name),
            EscapeSupport::attr($field->name),
            $value ? ' checked' : '',
            $this->attributes($field),
        );
    }

    private function checkboxList(Field $field, mixed $value): string
    {
        $selected = \is_array($value) ? array_map(strval(...), $value) : [];
        $items = [];

        foreach ($field->options as $optionValue => $label) {
            $items[] = sprintf(
                '<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
                EscapeSupport::attr($field->name),
                EscapeSupport::attr((string) $optionValue),
                \in_array((string) $optionValue, $selected, true) ? ' checked' : '',
                EscapeSupport::html($label),
            );
        }

        return '<fieldset>' . implode('<br>', $items) . '</fieldset>';
    }

    private function select(Field $field, mixed $value): string
    {
        $current = $this->scalar($value);
        $optionsHtml = '';

        foreach ($field->options as $optionValue => $label) {
            $optionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                EscapeSupport::attr((string) $optionValue),
                (string) $optionValue === $current ? ' selected' : '',
                EscapeSupport::html($label),
            );
        }

        return sprintf(
            '<select id="%s" name="%s"%s>%s</select>',
            EscapeSupport::attr($field->name),
            EscapeSupport::attr($field->name),
            $this->attributes($field),
            $optionsHtml,
        );
    }

    private function attributes(Field $field): string
    {
        $html = '';

        foreach ($field->attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', EscapeSupport::attr($name), EscapeSupport::attr($value));
        }

        return $html;
    }

    private function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
