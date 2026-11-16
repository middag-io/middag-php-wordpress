<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Settings;

use Middag\WordPress\Settings\SettingDefinition;
use Middag\WordPress\Settings\SettingsRegistrar;
use Middag\WordPress\Support\OptionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Settings registered through the WordPress Settings API and stored in the
 * real options table.
 *
 * @internal
 */
#[CoversClass(SettingsRegistrar::class)]
#[CoversClass(OptionSupport::class)]
final class SettingsRegistrarIntegrationTest extends TestCase
{
    private const string GROUP = 'middaglib_it_group';

    private const string TEXT_OPTION = 'middaglib_it_text';

    private const string NUMBER_OPTION = 'middaglib_it_number';

    protected function setUp(): void
    {
        parent::setUp();

        $registrar = new SettingsRegistrar();
        $registrar->add(new SettingDefinition(self::GROUP, self::TEXT_OPTION, 'fallback'));
        $registrar->add(new SettingDefinition(self::GROUP, self::NUMBER_OPTION, 1, sanitizer: static fn (mixed $value): int => max(1, (int) $value)));
        $registrar->register();
    }

    protected function tearDown(): void
    {
        unregister_setting(self::GROUP, self::TEXT_OPTION);
        unregister_setting(self::GROUP, self::NUMBER_OPTION);
        delete_option(self::TEXT_OPTION);
        delete_option(self::NUMBER_OPTION);
        wp_set_current_user(0);
        parent::tearDown();
    }

    #[Test]
    public function registeredSettingsCarryTheirGroupAndDefault(): void
    {
        $registered = get_registered_settings();

        self::assertSame(self::GROUP, $registered[self::TEXT_OPTION]['group'] ?? null);
        self::assertSame('fallback', get_option(self::TEXT_OPTION));
        self::assertSame(1, get_option(self::NUMBER_OPTION));
        // OptionSupport::get() always hands its own default to get_option(), so
        // the caller's default wins over the registered one.
        self::assertSame('caller', OptionSupport::get(self::TEXT_OPTION, 'caller'));
    }

    #[Test]
    public function aUserWithTheCapabilitySavesSanitizedValues(): void
    {
        wp_set_current_user(1);

        update_option(self::TEXT_OPTION, "  <b>Bold</b>\n text ");
        update_option(self::NUMBER_OPTION, '-5');

        self::assertSame('Bold text', get_option(self::TEXT_OPTION));
        self::assertSame('Bold text', OptionSupport::get(self::TEXT_OPTION, 'caller'));
        self::assertSame(1, get_option(self::NUMBER_OPTION));

        wp_cache_delete('alloptions', 'options');
        wp_cache_delete(self::TEXT_OPTION, 'options');
        self::assertSame('Bold text', $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
            "SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
            self::TEXT_OPTION,
        )));
    }

    #[Test]
    public function aUserWithoutTheCapabilityKeepsTheStoredValue(): void
    {
        wp_set_current_user(1);
        update_option(self::TEXT_OPTION, 'kept');

        wp_set_current_user(0);
        update_option(self::TEXT_OPTION, 'overwritten');

        self::assertSame('kept', get_option(self::TEXT_OPTION));
    }
}
