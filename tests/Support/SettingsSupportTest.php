<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\SettingsSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingsSupport::class)]
final class SettingsSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_settings'] = [];
        $GLOBALS['__wp_test_settings_sections'] = [];
        $GLOBALS['__wp_test_settings_fields'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_settings'],
            $GLOBALS['__wp_test_settings_sections'],
            $GLOBALS['__wp_test_settings_fields'],
        );
    }

    #[Test]
    public function registerSettingDelegatesToWordPress(): void
    {
        $sanitizer = static fn (mixed $v): mixed => $v;

        SettingsSupport::registerSetting('middag_group', 'middag_option', [
            'default' => 'fallback',
            'sanitize_callback' => $sanitizer,
        ]);

        $recorded = $GLOBALS['__wp_test_settings']['middag_option'] ?? null;
        self::assertNotNull($recorded, 'the setting was not registered');
        self::assertSame('middag_group', $recorded['group']);
        self::assertSame('fallback', $recorded['args']['default']);
        self::assertSame($sanitizer, $recorded['args']['sanitize_callback']);
    }

    #[Test]
    public function addSectionDelegatesToWordPress(): void
    {
        $callback = static function (): void {};

        SettingsSupport::addSection('middag_section', 'Section title', $callback, 'middag_page');

        $recorded = $GLOBALS['__wp_test_settings_sections']['middag_section'] ?? null;
        self::assertNotNull($recorded, 'the section was not registered');
        self::assertSame('Section title', $recorded['title']);
        self::assertSame('middag_page', $recorded['page']);
    }

    #[Test]
    public function addFieldDelegatesToWordPress(): void
    {
        $callback = static function (): void {};

        SettingsSupport::addField('middag_field', 'Field title', $callback, 'middag_page', 'middag_section');

        $recorded = $GLOBALS['__wp_test_settings_fields']['middag_field'] ?? null;
        self::assertNotNull($recorded, 'the field was not registered');
        self::assertSame('Field title', $recorded['title']);
        self::assertSame('middag_page', $recorded['page']);
        self::assertSame('middag_section', $recorded['section']);
    }
}
