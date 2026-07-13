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

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\NormalizesCapability;

/**
 * Immutable description of a single WordPress setting.
 *
 * Carries everything {@see SettingsRegistrar} needs to register an option and
 * (optionally) surface it as an admin field: the option group/name, a default,
 * the capability required to write it, an optional per-setting sanitizer, and
 * optional field placement metadata. A `null` sanitizer signals "use the
 * registrar's default sanitizer" (WP-04's `SanitizeSupport::sanitizeText`).
 *
 * @internal
 */
final readonly class SettingDefinition
{
    use NormalizesCapability;

    /**
     * Capability required to write the option (normalized to a plain string).
     */
    public string $capability;

    /**
     * @param non-empty-string           $optionGroup the register_setting() option group
     * @param non-empty-string           $optionName  the option name persisted via the WP options table
     * @param CapabilityInterface|string $capability  capability required to write it (raw string or typed)
     * @param null|callable              $sanitizer   per-setting sanitize callback; null = registrar default
     * @param null|non-empty-string      $page        admin page slug for the field (null = no field)
     * @param null|callable              $render      renders the field control (null = no field)
     */
    public function __construct(
        public string $optionGroup,
        public string $optionName,
        public mixed $default = '',
        CapabilityInterface|string $capability = 'manage_options',
        public mixed $sanitizer = null,
        public ?string $page = null,
        public string $section = 'default',
        public string $title = '',
        public mixed $render = null,
    ) {
        $this->capability = self::capabilityString($capability);
    }

    /**
     * Whether this setting should also be rendered as an admin field.
     */
    public function hasField(): bool
    {
        return $this->page !== null && is_callable($this->render);
    }
}
