<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Diagnostics\Fixture;

use Middag\WordPress\Runtime\Kernel;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Kernel subclass the diagnostics tests boot or leave unbooted.
 *
 * @internal
 */
final class DiagnosticsKernel extends Kernel
{
    private static ?self $kernel = null;

    protected static function kernel(): static
    {
        return self::$kernel ??= new self();
    }

    protected function buildContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): object
            {
                throw new RuntimeException('Not bound: ' . $id);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
