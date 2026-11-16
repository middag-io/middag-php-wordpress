<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Translation;

use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The translator against a text domain loaded by WordPress itself.
 *
 * @internal
 */
#[CoversClass(WpTranslator::class)]
final class WpTranslatorIntegrationTest extends TestCase
{
    public const string DOMAIN = 'middaglib-it';

    protected function setUp(): void
    {
        parent::setUp();
        self::loadTextDomain();
    }

    protected function tearDown(): void
    {
        unload_textdomain(self::DOMAIN);
        parent::tearDown();
    }

    public static function loadTextDomain(): void
    {
        // The .mo path is the WordPress convention; WordPress picks the
        // .l10n.php file next to it.
        self::assertTrue(load_textdomain(self::DOMAIN, __DIR__ . '/../Fixtures/languages/' . self::DOMAIN . '.mo'));
    }

    #[Test]
    public function translatesThroughTheLoadedTextDomain(): void
    {
        self::assertTrue(is_textdomain_loaded(self::DOMAIN));

        $translator = new WpTranslator(self::DOMAIN);

        self::assertSame('Olá Ada', $translator->get('Hello %name%', params: ['name' => 'Ada']));
        self::assertSame('Untranslated', $translator->get('Untranslated'));
        self::assertTrue($translator->has('Hello %name%'));
    }

    #[Test]
    public function pluralFormsFollowTheDomainRules(): void
    {
        $translator = new WpTranslator('middaglib');
        $key = '%count% item|%count% items';

        self::assertSame('1 item', $translator->get($key, self::DOMAIN, ['%count%' => 1]));
        self::assertSame('5 itens', $translator->get($key, self::DOMAIN, ['%count%' => 5]));
    }

    #[Test]
    public function anUnloadedDomainReturnsTheKey(): void
    {
        unload_textdomain(self::DOMAIN);

        self::assertSame('Hello Ada', (new WpTranslator(self::DOMAIN))->get('Hello %name%', params: ['name' => 'Ada']));
    }
}
