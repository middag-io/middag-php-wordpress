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

use Middag\WordPress\Settings\SettingDefinition;
use Middag\WordPress\Settings\SettingsRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingsRegistrar::class)]
#[CoversClass(SettingDefinition::class)]
final class SettingsRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_settings'] = [];
        $GLOBALS['__wp_test_settings_fields'] = [];
        $GLOBALS['__wp_test_caps'] = [];
        $GLOBALS['__wp_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_settings'],
            $GLOBALS['__wp_test_settings_fields'],
            $GLOBALS['__wp_test_caps'],
            $GLOBALS['__wp_test_options'],
        );
    }

    #[Test]
    public function registerPushesEachSettingToWordPress(): void
    {
        $registrar = new SettingsRegistrar();
        $registrar->add(new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_api_key',
            default: 'none',
        ));

        $registrar->register();

        $recorded = $GLOBALS['__wp_test_settings']['middag_api_key'] ?? null;
        self::assertNotNull($recorded, 'register() did not register the setting');
        self::assertSame('middag_group', $recorded['group']);
        self::assertSame('none', $recorded['args']['default']);
        self::assertIsCallable($recorded['args']['sanitize_callback']);
    }

    #[Test]
    public function registerAddsAFieldOnlyWhenTheDefinitionHasOne(): void
    {
        $registrar = new SettingsRegistrar();
        $registrar->add(new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_with_field',
            page: 'middag_page',
            title: 'API key',
            render: static function (): void {},
        ));
        $registrar->add(new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_no_field',
        ));

        $registrar->register();

        self::assertArrayHasKey('middag_with_field', $GLOBALS['__wp_test_settings_fields']);
        self::assertArrayNotHasKey('middag_no_field', $GLOBALS['__wp_test_settings_fields']);
        self::assertSame('API key', $GLOBALS['__wp_test_settings_fields']['middag_with_field']['title']);
        self::assertSame('middag_page', $GLOBALS['__wp_test_settings_fields']['middag_with_field']['page']);
    }

    #[Test]
    public function sanitizeCallbackRunsThePerSettingSanitizerForAPermittedUser(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        $definition = new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_slug',
            capability: 'manage_options',
            sanitizer: static fn (mixed $v): string => strtoupper((string) $v),
        );
        $registrar = new SettingsRegistrar();
        $callback = $registrar->sanitizeCallbackFor($definition);

        self::assertSame('HELLO', $callback('hello'));
    }

    #[Test]
    public function defaultSanitizerTextSanitizesAStringWhenNoSanitizerIsGiven(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        $definition = new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_text',
            capability: 'manage_options',
        );
        $registrar = new SettingsRegistrar();
        $callback = $registrar->sanitizeCallbackFor($definition);

        // The default sanitizer routes string scalars through
        // SanitizeSupport::text(): tags stripped, whitespace collapsed and
        // trimmed — unconditionally, not a raw passthrough.
        self::assertSame('raw value', $callback('  raw <b>value</b>  '));
    }

    #[Test]
    public function defaultSanitizerLeavesNonStringValuesUntouched(): void
    {
        $GLOBALS['__wp_test_caps']['manage_options'] = true;

        $definition = new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_int',
            capability: 'manage_options',
        );
        $registrar = new SettingsRegistrar();
        $callback = $registrar->sanitizeCallbackFor($definition);

        // Non-string values pass through; callers persisting typed options must
        // supply an explicit sanitizer.
        self::assertSame(42, $callback(42));
    }

    #[Test]
    public function capabilityGuardBlocksUnauthorizedWritesByReturningTheStoredValue(): void
    {
        // current_user_can() returns false (no caps granted) → unauthorized.
        $GLOBALS['__wp_test_options']['middag_protected'] = 'stored-value';

        $definition = new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_protected',
            default: 'the-default',
            capability: 'manage_options',
            sanitizer: static fn (mixed $v): string => 'MUTATED-' . $v,
        );
        $registrar = new SettingsRegistrar();
        $callback = $registrar->sanitizeCallbackFor($definition);

        $result = $callback('attacker-supplied');

        // The sanitizer must NOT have run; the previously stored value is kept.
        self::assertSame('stored-value', $result);
    }

    #[Test]
    public function capabilityGuardReturnsTheDefaultWhenNothingIsStored(): void
    {
        // Unauthorized and no stored option → fall back to the definition default.
        $definition = new SettingDefinition(
            optionGroup: 'middag_group',
            optionName: 'middag_unset',
            default: 'the-default',
            capability: 'manage_options',
            sanitizer: static fn (mixed $v): string => 'MUTATED',
        );
        $registrar = new SettingsRegistrar();
        $callback = $registrar->sanitizeCallbackFor($definition);

        self::assertSame('the-default', $callback('attacker-supplied'));
    }
}
