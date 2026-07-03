<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Translation;

use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpTranslator::class)]
final class WpTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_translations'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_translations']);
    }

    #[Test]
    public function getTranslatesThroughTheDefaultDomain(): void
    {
        $GLOBALS['__wp_test_translations']['middag']['greeting'] = 'Olá';

        self::assertSame('Olá', (new WpTranslator())->get('greeting'));
    }

    #[Test]
    public function componentMapsToTheTextDomain(): void
    {
        $GLOBALS['__wp_test_translations']['myplugin']['greeting'] = 'Oi';
        $GLOBALS['__wp_test_translations']['middag']['greeting'] = 'Olá';

        self::assertSame('Oi', (new WpTranslator())->get('greeting', 'myplugin'));
    }

    #[Test]
    public function untranslatedKeysRoundTripUnchanged(): void
    {
        self::assertSame('plain message', (new WpTranslator())->get('plain message'));
    }

    #[Test]
    public function countPicksThePluralFormOfAPipeMessage(): void
    {
        $translator = new WpTranslator();

        self::assertSame('2 items', $translator->get('%count% item|%count% items', '', ['%count%' => 2]));
        self::assertSame('1 item', $translator->get('%count% item|%count% items', '', ['%count%' => 1]));
    }

    #[Test]
    public function bareCountParamAlsoDrivesPluralisation(): void
    {
        self::assertSame('3 items', (new WpTranslator())->get('%count% item|%count% items', '', ['count' => 3]));
    }

    #[Test]
    public function pluralFormsCanBeTranslatedPerDomain(): void
    {
        $GLOBALS['__wp_test_translations']['middag']['%count% item|%count% items'] = ['%count% item', '%count% itens'];

        self::assertSame('5 itens', (new WpTranslator())->get('%count% item|%count% items', '', ['%count%' => 5]));
    }

    #[Test]
    public function namedParamsAreInterpolated(): void
    {
        $message = (new WpTranslator())->get('Hello %name%, you have %count% tasks', '', [
            'name' => 'Ana',
            '%count%' => 4,
        ]);

        self::assertSame('Hello Ana, you have 4 tasks', $message);
    }

    #[Test]
    public function nonScalarParamsInterpolateAsEmptyStringsUnlessStringable(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'S';
            }
        };

        $translator = new WpTranslator();

        self::assertSame('v=S', $translator->get('v=%obj%', '', ['obj' => $stringable]));
        self::assertSame('v=', $translator->get('v=%obj%', '', ['obj' => ['not', 'stringable']]));
    }

    #[Test]
    public function hasTreatsAnyNonEmptyKeyAsPresent(): void
    {
        $translator = new WpTranslator();

        self::assertTrue($translator->has('anything'));
        self::assertFalse($translator->has(''));
    }
}
