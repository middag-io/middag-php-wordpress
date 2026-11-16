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

use InvalidArgumentException;
use Middag\WordPress\Diagnostics\DiagnosticsRegistry;
use Middag\WordPress\Diagnostics\DoctorCommand;
use Middag\WordPress\Diagnostics\DoctorCommandRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctorCommandRegistrar::class)]
final class DoctorCommandRegistrarTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_cli_commands']);
    }

    #[Test]
    public function defaultsToTheNeutralRoot(): void
    {
        self::assertSame('middaglib doctor', (new DoctorCommandRegistrar($this->command()))->commandName());
    }

    #[Test]
    public function theRootIsConfigurable(): void
    {
        self::assertSame('acme doctor', (new DoctorCommandRegistrar($this->command(), 'acme'))->commandName());
    }

    #[Test]
    public function outsideWpCliNothingIsRegistered(): void
    {
        self::assertFalse(defined('WP_CLI'), 'this test needs a process without the WP_CLI constant');
        self::assertFalse((new DoctorCommandRegistrar($this->command(), 'acme'))->register());
        self::assertArrayNotHasKey('__wp_test_cli_commands', $GLOBALS);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function underWpCliTheCommandIsAddedUnderItsRoot(): void
    {
        define('WP_CLI', true);
        $command = $this->command();

        self::assertTrue((new DoctorCommandRegistrar($command, 'acme'))->register());

        $registered = $GLOBALS['__wp_test_cli_commands']['acme doctor'] ?? null;
        self::assertIsArray($registered);
        self::assertSame($command, $registered['callable']);
        self::assertArrayHasKey('shortdesc', $registered['args']);
    }

    #[Test]
    #[DataProvider('invalidRootProvider')]
    public function anInvalidRootIsRejected(string $root): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DoctorCommandRegistrar($this->command(), $root);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRootProvider(): array
    {
        return [
            'empty' => [''],
            'two words' => ['acme shop'],
            'uppercase' => ['Acme'],
            'leading dash' => ['-acme'],
        ];
    }

    private function command(): DoctorCommand
    {
        return new DoctorCommand(new DiagnosticsRegistry('acme_diagnostics'), [], static function (string $line): void {});
    }
}
