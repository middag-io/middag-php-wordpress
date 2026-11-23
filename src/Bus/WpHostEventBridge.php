<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Bus;

use Middag\Framework\Kernel\Contract\HostEventBridgeInterface;
use Middag\Framework\Kernel\Manager\HookManager;
use Middag\WordPress\Support\HookSupport;

/**
 * WordPress host event bridge.
 *
 * Implements the framework's platform-agnostic {@see HostEventBridgeInterface}
 * seam by exposing the host's NATIVE eventing — named events become WordPress
 * action hooks, so `add_action()` consumers anywhere in the site (satellite
 * plugins, themes) hear governed events without importing MIDDAG code:
 *
 *     dispatch('quote.created', [$quote]) → do_action('acme/quote.created', $quote)
 *     listen('quote.created', $callback)  → add_action('acme/quote.created', $callback)
 *
 * (with the hook prefix `acme/`).
 *
 * This is the WordPress counterpart of `MoodleHostEventBridge`, which delegates
 * to the framework's in-memory {@see HookManager}
 * instead — WordPress already has the hooks API this contract describes, so the
 * adapter goes native. The payload is spread as positional arguments (keys are ignored).
 *
 * The hook prefix is a constructor argument. Its default is the neutral
 * library marker `middaglib/`, which names no product: a consumer passes the
 * prefix of its own public hook surface in its composition root.
 *
 * @api
 */
final readonly class WpHostEventBridge implements HostEventBridgeInterface
{
    public function __construct(
        private string $hookPrefix = 'middaglib/',
    ) {}

    public function dispatch(string $eventName, array $payload = []): void
    {
        // array_values(): a string-keyed payload would spread as named arguments.
        HookSupport::doAction($this->hook($eventName), ...array_values($payload));
    }

    public function listen(string $eventName, callable $listener, int $priority = 10): void
    {
        HookSupport::addAction($this->hook($eventName), $listener, $priority, PHP_INT_MAX);
    }

    private function hook(string $eventName): string
    {
        return $this->hookPrefix . $eventName;
    }
}
