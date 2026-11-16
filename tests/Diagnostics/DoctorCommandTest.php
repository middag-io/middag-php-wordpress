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

use Middag\WordPress\Diagnostics\BootReport;
use Middag\WordPress\Diagnostics\BootReportRenderer;
use Middag\WordPress\Diagnostics\CallbackCheck;
use Middag\WordPress\Diagnostics\DiagnosticsRegistry;
use Middag\WordPress\Diagnostics\DoctorCommand;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\PackageProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctorCommand::class)]
#[CoversClass(BootReportRenderer::class)]
final class DoctorCommandTest extends TestCase
{
    private const string FILTER = 'acme_diagnostics';

    /** @var list<string> */
    private array $lines = [];

    protected function setUp(): void
    {
        $this->lines = [];
        $GLOBALS['__middag_test_wp_filters'] = [];
        unset($GLOBALS['__wp_test_cli_halt'], $GLOBALS['__wp_test_cli_lines']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_wp_filters'], $GLOBALS['__wp_test_cli_halt'], $GLOBALS['__wp_test_cli_lines']);
    }

    #[Test]
    public function aHealthyReportExitsZeroAndStatesItCoversBootOnly(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop', '1.4.0', entryClass: DoctorCommand::class));

        $code = $this->command()->run();

        self::assertSame(BootReport::EXIT_HEALTHY, $code);
        self::assertSame('acme-shop 1.4.0: PASS', $this->lines[0]);
        self::assertContains(BootReportRenderer::BOOT_ONLY_NOTICE, $this->lines);
        self::assertSame('Status: PASS (1 package(s))', end($this->lines));
    }

    #[Test]
    public function aFailedCheckExitsNonZero(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop', '1.4.0', checks: [
            new CallbackCheck('admin-shell', CheckScope::Render, static fn (): string => 'no <head> in admin page'),
        ]));

        self::assertSame(BootReport::EXIT_BROKEN, $this->command()->run());
        self::assertContains('  [fail] admin-shell (render): no <head> in admin page', $this->lines);
        self::assertContains(BootReportRenderer::BOOT_AND_RENDER_NOTICE, $this->lines);
    }

    #[Test]
    public function expectedPackagesFromConstructorAndOptionAreMerged(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop', '1.4.0', entryClass: DoctorCommand::class));

        $code = $this->command(['acme-shop'])->run(['expect' => ' acme-quotes ,,acme-shop']);

        self::assertSame(BootReport::EXIT_BROKEN, $code);
        self::assertContains('Issues:', $this->lines);
        self::assertContains('  [fail] expected package "acme-quotes" is not registered (did it load?)', $this->lines);
        self::assertNotContains('  [fail] expected package "acme-shop" is not registered (did it load?)', $this->lines);
    }

    #[Test]
    public function strictTurnsWarningsIntoAFailure(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop'));

        self::assertSame(BootReport::EXIT_HEALTHY, $this->command()->run());
        self::assertSame(BootReport::EXIT_BROKEN, $this->command()->run(['strict' => true]));
        self::assertSame(BootReport::EXIT_BROKEN, $this->command()->run(['strict' => 'true']));
    }

    #[Test]
    public function jsonFormatPrintsOneDocument(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop', '1.4.0', entryClass: DoctorCommand::class));

        $code = $this->command()->run(['format' => 'json']);

        self::assertSame(BootReport::EXIT_HEALTHY, $code);
        self::assertCount(1, $this->lines);
        $decoded = json_decode($this->lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pass', $decoded['status']);
        self::assertSame(['boot'], $decoded['covers']);
    }

    #[Test]
    public function anUnknownFormatIsAUsageError(): void
    {
        self::assertSame(DoctorCommand::EXIT_USAGE, $this->command()->run(['format' => 'yaml']));
        self::assertSame(['Invalid --format; use one of: text, json.'], $this->lines);
    }

    #[Test]
    public function invokeHaltsWithTheExitCode(): void
    {
        $this->command()->__invoke([], []);

        self::assertSame(BootReport::EXIT_BROKEN, $GLOBALS['__wp_test_cli_halt'] ?? null);
    }

    #[Test]
    public function theDefaultWriterPrintsThroughWpCli(): void
    {
        $this->registerProbes(new PackageProbe('acme-shop', '1.4.0', entryClass: DoctorCommand::class));

        (new DoctorCommand(new DiagnosticsRegistry(self::FILTER)))->run();

        self::assertSame('acme-shop 1.4.0: PASS', $GLOBALS['__wp_test_cli_lines'][0] ?? null);
    }

    private function registerProbes(PackageProbe ...$probes): void
    {
        $GLOBALS['__middag_test_wp_filters'][self::FILTER] = static fn (array $value): array => [...$value, ...$probes];
    }

    /**
     * @param list<string> $expected
     */
    private function command(array $expected = []): DoctorCommand
    {
        return new DoctorCommand(
            new DiagnosticsRegistry(self::FILTER),
            $expected,
            function (string $line): void {
                $this->lines[] = $line;
            },
        );
    }
}
