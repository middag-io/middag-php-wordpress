<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Hook\Contract;

use Middag\WordPress\Hook\Contract\HookInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversNothing]
final class HookInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(HookInterface::class));
    }

    #[Test]
    public function interfaceDeclaresRegisterMethod(): void
    {
        $reflection = new ReflectionClass(HookInterface::class);

        self::assertTrue($reflection->hasMethod('register'));
    }

    #[Test]
    public function registerMethodReturnsVoid(): void
    {
        $reflection = new ReflectionClass(HookInterface::class);
        $method = $reflection->getMethod('register');

        self::assertTrue($method->hasReturnType());
        self::assertSame('void', $method->getReturnType()->getName());
    }

    #[Test]
    public function registerMethodAcceptsNoParameters(): void
    {
        $reflection = new ReflectionClass(HookInterface::class);
        $method = $reflection->getMethod('register');

        self::assertCount(0, $method->getParameters());
    }

    #[Test]
    public function registerMethodIsPublic(): void
    {
        $reflection = new ReflectionClass(HookInterface::class);
        $method = $reflection->getMethod('register');

        self::assertTrue($method->isPublic());
    }
}
