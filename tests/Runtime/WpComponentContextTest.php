<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Runtime;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpComponentContext::class)]
final class WpComponentContextTest extends TestCase
{
    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(
            HostComponentContextInterface::class,
            new WpComponentContext('my-plugin', '1.2.3'),
        );
    }

    #[Test]
    public function exposesTheConfiguredValues(): void
    {
        $context = new WpComponentContext('my-plugin', '1.2.3', '/srv/my-plugin');

        self::assertSame('my-plugin', $context->componentName());
        self::assertSame('1.2.3', $context->assetVersion());
        self::assertSame('/srv/my-plugin', $context->basePath());
    }

    #[Test]
    public function basePathDefaultsToNull(): void
    {
        $context = new WpComponentContext('my-plugin', '1.2.3');

        self::assertNull($context->basePath());
    }
}
