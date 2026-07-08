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
use Middag\WordPress\Settings\SettingsPageRegistrar;
use Middag\WordPress\Settings\SettingsRegistrar;
use Middag\WordPress\Settings\Tab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingsPageRegistrar::class)]
final class SettingsPageRegistrarTest extends TestCase
{
    private SettingsRegistrar $settings;

    private SettingsPageRegistrar $registrar;

    protected function setUp(): void
    {
        $this->settings = new SettingsRegistrar();
        $this->registrar = new SettingsPageRegistrar($this->settings);
        $GLOBALS['__wp_test_settings'] = [];
        $GLOBALS['__wp_test_settings_sections'] = [];
        $GLOBALS['__wp_test_settings_fields'] = [];
        $GLOBALS['__wp_test_options'] = [];
        $GLOBALS['__wp_test_current_user_can'] = true;
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_settings'],
            $GLOBALS['__wp_test_settings_sections'],
            $GLOBALS['__wp_test_settings_fields'],
            $GLOBALS['__wp_test_options'],
            $GLOBALS['__wp_test_current_user_can'],
        );
    }

    #[Test]
    public function registersSettingsSectionsAndFieldsPerTabPage(): void
    {
        $this->registrar->register('acme', 'acme_group', [
            new Tab('general', 'General', [
                new Section('acme_core', 'Core', [
                    new Field('acme_name', 'Name'),
                    new Field('acme_flag', 'Flag', FieldType::Checkbox),
                ]),
            ]),
            new Tab('advanced', 'Advanced', [
                new Section('acme_adv', 'Advanced', [
                    new Field('acme_limit', 'Limit', FieldType::Number),
                ]),
            ]),
        ]);

        self::assertCount(3, $this->settings->all());

        $definitions = $this->settings->all();
        self::assertSame('acme-general', $definitions[0]->page);
        self::assertSame('acme_core', $definitions[0]->section);
        self::assertSame('acme-advanced', $definitions[2]->page);

        self::assertArrayHasKey('acme_name', $GLOBALS['__wp_test_settings']);
        self::assertArrayHasKey('acme_limit', $GLOBALS['__wp_test_settings']);
    }

    #[Test]
    public function typeDefaultSanitizerIsWiredIntoTheDefinition(): void
    {
        $this->registrar->register('acme', 'acme_group', [
            new Tab('general', 'General', [
                new Section('acme_core', 'Core', [
                    new Field('acme_flag', 'Flag', FieldType::Checkbox),
                ]),
            ]),
        ]);

        $sanitizer = $this->settings->all()[0]->sanitizer;

        self::assertIsCallable($sanitizer);
        self::assertSame('1', $sanitizer('yes'));
        self::assertSame('', $sanitizer(''));
    }

    #[Test]
    public function tabPageConventionIsStable(): void
    {
        $tab = new Tab('general', 'General', []);

        self::assertSame('acme-general', $this->registrar->tabPage('acme', $tab));
    }
}
