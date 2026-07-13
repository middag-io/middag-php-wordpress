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

use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\PhpSapiEmitter;
use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Support\EscapeSupport;
use Middag\WordPress\Support\SettingsSupport;

/**
 * Declarative settings pages: describe tabs → sections → {@see Field}s once
 * and this registrar wires everything through the WordPress Settings API —
 * `register_setting()` (with the field's type-default sanitizer),
 * `add_settings_section()` and `add_settings_field()` (rendered by
 * {@see FieldRenderer}).
 *
 * Each tab registers under `{page}-{tabSlug}`; the consumer's page callback
 * renders the active tab with `settings_fields($optionGroup)` +
 * `do_settings_sections("{page}-{tabSlug}")`. Replaces the hand-rolled
 * settings frameworks every plugin reinvents.
 *
 * @api
 */
final readonly class SettingsPageRegistrar
{
    public function __construct(
        private SettingsRegistrar $settings,
        private FieldRenderer $renderer = new FieldRenderer(),
        private ResponseEmitterInterface $emitter = new PhpSapiEmitter(),
    ) {}

    /**
     * Stage and register every tab of a settings page.
     *
     * @param non-empty-string           $page        settings page slug (menu wiring is the consumer's concern)
     * @param non-empty-string           $optionGroup register_setting() option group
     * @param list<Tab>                  $tabs
     * @param CapabilityInterface|string $capability  capability required to WRITE the options (raw string or typed)
     */
    public function register(string $page, string $optionGroup, array $tabs, CapabilityInterface|string $capability = 'manage_options'): void
    {
        foreach ($tabs as $tab) {
            $tabPage = $this->tabPage($page, $tab);

            foreach ($tab->sections as $section) {
                $emitter = $this->emitter;
                SettingsSupport::addSection(
                    $section->id,
                    $section->title,
                    static function () use ($section, $emitter): void {
                        if ($section->description !== '') {
                            $emitter->write('<p>' . EscapeSupport::html($section->description) . '</p>');
                        }
                    },
                    $tabPage,
                );

                foreach ($section->fields as $field) {
                    $this->settings->add(new SettingDefinition(
                        optionGroup: $optionGroup,
                        optionName: $field->name,
                        default: $field->default,
                        capability: $capability,
                        sanitizer: $field->resolveSanitizer(),
                        page: $tabPage,
                        section: $section->id,
                        title: $field->title,
                        render: fn (): string => $this->echoField($field),
                    ));
                }
            }
        }

        $this->settings->register();
    }

    /**
     * The settings-page slug a tab's sections register under.
     */
    public function tabPage(string $page, Tab $tab): string
    {
        return $page . '-' . $tab->slug;
    }

    private function echoField(Field $field): string
    {
        $html = $this->renderer->render($field);
        $this->emitter->write($html);

        return $html;
    }
}
