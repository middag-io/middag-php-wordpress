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

use Middag\WordPress\Support\LifecycleSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LifecycleSupport::class)]
final class LifecycleSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_activation_hooks'] = [];
        $GLOBALS['__wp_test_deactivation_hooks'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_activation_hooks'],
            $GLOBALS['__wp_test_deactivation_hooks'],
        );
    }

    #[Test]
    public function registerActivationDelegatesToWordPress(): void
    {
        $callback = static fn (): null => null;

        LifecycleSupport::registerActivation('/plugins/middag/middag.php', $callback);

        $registered = $GLOBALS['__wp_test_activation_hooks']['/plugins/middag/middag.php'][0] ?? null;
        self::assertSame($callback, $registered, 'the activation callback was not registered');
    }

    #[Test]
    public function registerDeactivationDelegatesToWordPress(): void
    {
        $callback = static fn (): null => null;

        LifecycleSupport::registerDeactivation('/plugins/middag/middag.php', $callback);

        $registered = $GLOBALS['__wp_test_deactivation_hooks']['/plugins/middag/middag.php'][0] ?? null;
        self::assertSame($callback, $registered, 'the deactivation callback was not registered');
    }

    #[Test]
    public function registerActivationKeysCallbacksByPluginFile(): void
    {
        LifecycleSupport::registerActivation('/plugins/a/a.php', static fn (): null => null);
        LifecycleSupport::registerActivation('/plugins/b/b.php', static fn (): null => null);

        self::assertArrayHasKey('/plugins/a/a.php', $GLOBALS['__wp_test_activation_hooks']);
        self::assertArrayHasKey('/plugins/b/b.php', $GLOBALS['__wp_test_activation_hooks']);
    }
}
