<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Bus;

use Middag\Framework\Kernel\Contract\HostEventBridgeInterface;
use Middag\WordPress\Bus\WpHostEventBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(WpHostEventBridge::class)]
final class WpHostEventBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_actions'] = [];
        $GLOBALS['__wp_test_dispatched'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_actions'], $GLOBALS['__wp_test_dispatched']);
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(HostEventBridgeInterface::class, new WpHostEventBridge());
    }

    #[Test]
    public function dispatchBroadcastsANativePrefixedAction(): void
    {
        $quote = new stdClass();

        (new WpHostEventBridge())->dispatch('quote.created', [$quote, 42]);

        self::assertCount(1, $GLOBALS['__wp_test_dispatched']);
        self::assertSame('middag/quote.created', $GLOBALS['__wp_test_dispatched'][0]['hook']);
        self::assertSame([$quote, 42], $GLOBALS['__wp_test_dispatched'][0]['args']);
    }

    #[Test]
    public function listenRegistersANativePrefixedActionCallback(): void
    {
        (new WpHostEventBridge())->listen('quote.created', static function (): void {}, 20);

        self::assertArrayHasKey('middag/quote.created', $GLOBALS['__wp_test_actions']);
        self::assertSame(20, $GLOBALS['__wp_test_actions']['middag/quote.created'][0]['priority']);
    }

    #[Test]
    public function listenersReceiveTheFullPositionalPayload(): void
    {
        $bridge = new WpHostEventBridge();
        $received = null;

        $bridge->listen('order.paid', static function (mixed ...$args) use (&$received): void {
            $received = $args;
        });
        $bridge->dispatch('order.paid', ['order-1', 99.9, true]);

        self::assertSame(['order-1', 99.9, true], $received);
    }

    #[Test]
    public function theHookPrefixIsConfigurable(): void
    {
        (new WpHostEventBridge('acme/'))->dispatch('thing.happened');

        self::assertSame('acme/thing.happened', $GLOBALS['__wp_test_dispatched'][0]['hook']);
    }
}
