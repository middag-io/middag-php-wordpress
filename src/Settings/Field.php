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

/**
 * Declarative description of one admin settings field: the persisted option
 * name, the control to render and how to sanitize it on save.
 *
 * `options` feeds Select/CheckboxList choices (`value => label`); `attributes`
 * become extra HTML attributes on the control (`placeholder`, `min`, ...);
 * `html` is the literal markup for {@see FieldType::RawHtml}.
 *
 * @api
 */
final readonly class Field
{
    /**
     * @param non-empty-string      $name       option name persisted in wp_options
     * @param array<string, string> $options    choices for Select/CheckboxList
     * @param array<string, string> $attributes extra HTML attributes for the control
     * @param null|callable         $sanitizer  save-time sanitizer; null = the type default
     */
    public function __construct(
        public string $name,
        public string $title,
        public FieldType $type = FieldType::Text,
        public mixed $default = '',
        public array $options = [],
        public string $description = '',
        public array $attributes = [],
        public mixed $sanitizer = null,
        public string $html = '',
    ) {}

    /**
     * The save-time sanitizer: the field's own, or its type default.
     */
    public function resolveSanitizer(): callable
    {
        $sanitizer = $this->sanitizer;

        return is_callable($sanitizer) ? $sanitizer : $this->type->defaultSanitizer();
    }
}
