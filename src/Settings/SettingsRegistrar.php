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

use Middag\WordPress\Support\OptionSupport;
use Middag\WordPress\Support\SanitizeSupport;
use Middag\WordPress\Support\SettingsSupport;
use Middag\WordPress\Support\UserSupport;

/**
 * Registers WordPress settings (and optional admin fields) through the
 * {@see SettingsSupport} seam.
 *
 * Mirrors the collect-then-`register()` shape of the cron registrar: callers
 * stage {@see SettingDefinition}s with {@see add()}, then {@see register()}
 * pushes them to WordPress. Each setting is registered with a `sanitize_callback`
 * (see {@see sanitizeCallbackFor()}) that runs at save time and:
 *
 *  1. enforces the setting's capability via {@see UserSupport::currentUserCan()},
 *     returning the previously stored value when the current user is not
 *     permitted — the WordPress idiom for rejecting a write without an error; and
 *  2. otherwise applies the setting's sanitizer, or the registrar default —
 *     WP-04's {@see SanitizeSupport::text()} for string scalars.
 *
 * The `sanitize_callback` is defense-in-depth: WordPress already enforces the
 * options-page capability + nonce before it fires, and it is not guaranteed to
 * run on programmatic `update_option()` writes.
 *
 * @internal
 */
final class SettingsRegistrar
{
    /**
     * @var array<int, SettingDefinition>
     */
    private array $settings = [];

    public function add(SettingDefinition $definition): void
    {
        $this->settings[] = $definition;
    }

    /**
     * @return array<int, SettingDefinition>
     */
    public function all(): array
    {
        return $this->settings;
    }

    /**
     * Push every staged setting (and its optional admin field) to WordPress.
     */
    public function register(): void
    {
        foreach ($this->settings as $definition) {
            SettingsSupport::registerSetting(
                $definition->optionGroup,
                $definition->optionName,
                [
                    'default' => $definition->default,
                    'sanitize_callback' => $this->sanitizeCallbackFor($definition),
                ],
            );

            if (!$definition->hasField()) {
                continue;
            }

            $render = $definition->render;
            if (is_callable($render)) {
                SettingsSupport::addField(
                    $definition->optionName,
                    $definition->title,
                    $render,
                    (string) $definition->page,
                    $definition->section,
                );
            }
        }
    }

    /**
     * Build the capability-guarded sanitize callback WordPress invokes on save.
     *
     * The returned closure receives the incoming (unsanitized) value. When the
     * current user lacks the setting's capability it returns the previously
     * stored value (the WP idiom for "reject this write"); otherwise it runs the
     * per-setting sanitizer or the registrar default.
     */
    public function sanitizeCallbackFor(SettingDefinition $definition): callable
    {
        return function (mixed $value) use ($definition): mixed {
            if (!UserSupport::currentUserCan($definition->capability)) {
                return $this->storedValue($definition);
            }

            return $this->applySanitizer($definition, $value);
        };
    }

    /**
     * Apply the definition's sanitizer, or the registrar default for a value
     * with none: string scalars are text-sanitized through the WP-04 seam;
     * non-string values pass through unchanged (callers persisting
     * structured/typed options must supply an explicit sanitizer).
     */
    private function applySanitizer(SettingDefinition $definition, mixed $value): mixed
    {
        $sanitizer = $definition->sanitizer;
        if (is_callable($sanitizer)) {
            return $sanitizer($value);
        }

        return is_string($value) ? SanitizeSupport::text($value) : $value;
    }

    /**
     * The value currently persisted for a setting, used to reject an
     * unauthorized write without mutating the option.
     */
    private function storedValue(SettingDefinition $definition): mixed
    {
        return OptionSupport::get($definition->optionName, $definition->default);
    }
}
