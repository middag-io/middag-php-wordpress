<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Diagnostics;

use Middag\WordPress\Diagnostics\CallbackCheck;
use Middag\WordPress\Diagnostics\CheckResult;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallbackCheck::class)]
final class CallbackCheckTest extends TestCase
{
    #[Test]
    public function trueIsAPassInTheDeclaredScope(): void
    {
        $check = new CallbackCheck('admin-shell', CheckScope::Render, static fn (): bool => true);

        $result = $check->run();

        self::assertSame('admin-shell', $check->name());
        self::assertSame(CheckScope::Render, $check->scope());
        self::assertSame(CheckStatus::Pass, $result->status);
        self::assertSame(CheckScope::Render, $result->scope);
    }

    #[Test]
    public function falseAndAStringAreFailures(): void
    {
        $false = (new CallbackCheck('schema', CheckScope::Boot, static fn (): bool => false))->run();
        $message = (new CallbackCheck('schema', CheckScope::Boot, static fn (): string => 'table acme_orders is missing'))->run();

        self::assertSame(CheckStatus::Fail, $false->status);
        self::assertSame('check returned false', $false->message);
        self::assertSame(CheckStatus::Fail, $message->status);
        self::assertSame('table acme_orders is missing', $message->message);
    }

    #[Test]
    public function aFullResultIsReturnedAsIs(): void
    {
        $expected = CheckResult::warn('queue', '3 overdue jobs');

        self::assertSame($expected, (new CallbackCheck('queue', CheckScope::Boot, static fn (): CheckResult => $expected))->run());
    }
}
