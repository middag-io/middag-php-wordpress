<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Hook;

use Middag\WordPress\Bus\WpHostEventBridge;
use Middag\WordPress\Support\HookSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The host event bridge and the hook helpers on the WordPress Plugin API.
 *
 * @internal
 */
#[CoversClass(WpHostEventBridge::class)]
#[CoversClass(HookSupport::class)]
final class WpHostEventBridgeIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_actions('middaglib/it.order_paid');
        remove_all_actions('middaglib_it/it.order_paid');
        remove_all_filters('middaglib_it_title');
        parent::tearDown();
    }

    #[Test]
    public function listenAndDispatchUseTheWordPressActionWithEveryPayloadArgument(): void
    {
        $bridge = new WpHostEventBridge();
        $calls = [];

        $bridge->listen('it.order_paid', static function (mixed ...$args) use (&$calls): void {
            $calls[] = ['late', $args];
        }, 20);
        $bridge->listen('it.order_paid', static function (mixed ...$args) use (&$calls): void {
            $calls[] = ['early', $args];
        }, 5);

        self::assertTrue(has_action('middaglib/it.order_paid'));

        $bridge->dispatch('it.order_paid', [42, 'paid', ['total' => 10]]);

        self::assertSame([
            ['early', [42, 'paid', ['total' => 10]]],
            ['late', [42, 'paid', ['total' => 10]]],
        ], $calls);
        self::assertSame(1, did_action('middaglib/it.order_paid'));
    }

    #[Test]
    public function aCustomPrefixNamesTheWordPressHook(): void
    {
        $received = null;
        add_action('middaglib_it/it.order_paid', static function (int $id) use (&$received): void {
            $received = $id;
        });

        (new WpHostEventBridge('middaglib_it/'))->dispatch('it.order_paid', [7]);

        self::assertSame(7, $received);
    }

    #[Test]
    public function filtersRunThroughWordPress(): void
    {
        HookSupport::addFilter('middaglib_it_title', static fn (string $title, string $suffix): string => $title . $suffix, 10, 2);

        self::assertSame('Title!', HookSupport::applyFilters('middaglib_it_title', 'Title', '!'));
        self::assertSame('Title!', apply_filters('middaglib_it_title', 'Title', '!'));
    }
}
