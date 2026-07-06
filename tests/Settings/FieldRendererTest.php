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

use InvalidArgumentException;
use Middag\WordPress\Settings\Field;
use Middag\WordPress\Settings\FieldRenderer;
use Middag\WordPress\Settings\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FieldRenderer::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
final class FieldRendererTest extends TestCase
{
    private FieldRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FieldRenderer();
        $GLOBALS['__wp_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_options']);
    }

    #[Test]
    public function textInputRendersCurrentValueEscaped(): void
    {
        $GLOBALS['__wp_test_options']['acme_name'] = 'a"b<c>';

        $html = $this->renderer->render(new Field('acme_name', 'Name'));

        self::assertStringContainsString('type="text"', $html);
        self::assertStringContainsString('name="acme_name"', $html);
        self::assertStringContainsString('value="a&quot;b&lt;c&gt;"', $html);
    }

    #[Test]
    public function checkboxIsCheckedWhenOptionTruthy(): void
    {
        $GLOBALS['__wp_test_options']['acme_flag'] = '1';

        $html = $this->renderer->render(new Field('acme_flag', 'Flag', FieldType::Checkbox));

        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString(' checked', $html);
    }

    #[Test]
    public function selectMarksTheCurrentChoiceAndEscapesLabels(): void
    {
        $GLOBALS['__wp_test_options']['acme_mode'] = 'b';

        $html = $this->renderer->render(new Field(
            'acme_mode',
            'Mode',
            FieldType::Select,
            options: ['a' => 'Plan A', 'b' => 'Plan <B>'],
        ));

        self::assertStringContainsString('<option value="b" selected>Plan &lt;B&gt;</option>', $html);
        self::assertStringContainsString('<option value="a">Plan A</option>', $html);
    }

    #[Test]
    public function checkboxListChecksSelectedValues(): void
    {
        $GLOBALS['__wp_test_options']['acme_feats'] = ['x'];

        $html = $this->renderer->render(new Field(
            'acme_feats',
            'Features',
            FieldType::CheckboxList,
            options: ['x' => 'X', 'y' => 'Y'],
        ));

        self::assertStringContainsString('name="acme_feats[]" value="x" checked', $html);
        self::assertStringContainsString('name="acme_feats[]" value="y"> Y', $html);
    }

    #[Test]
    public function descriptionAndCustomAttributesAreEmitted(): void
    {
        $html = $this->renderer->render(new Field(
            'acme_limit',
            'Limit',
            FieldType::Number,
            description: 'Max items',
            attributes: ['min' => '1'],
        ));

        self::assertStringContainsString('type="number"', $html);
        self::assertStringContainsString(' min="1"', $html);
        self::assertStringContainsString('<p class="description">Max items</p>', $html);
    }

    #[Test]
    public function rawHtmlPassesThroughUntouched(): void
    {
        $html = $this->renderer->render(new Field('acme_raw', 'Raw', FieldType::RawHtml, html: '<hr class="x">'));

        self::assertStringContainsString('<hr class="x">', $html);
    }

    #[Test]
    public function invalidAttributeNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->renderer->render(new Field(
            'acme_evil',
            'Evil',
            FieldType::Number,
            attributes: ['x onload=alert(1)' => '1'],
        ));
    }

    #[Test]
    public function dataAndAriaAttributeNamesAreAllowed(): void
    {
        $html = $this->renderer->render(new Field(
            'acme_ok',
            'Ok',
            FieldType::Number,
            attributes: ['data-max' => '9', 'aria-label' => 'items'],
        ));

        self::assertStringContainsString(' data-max="9"', $html);
        self::assertStringContainsString(' aria-label="items"', $html);
    }
}
