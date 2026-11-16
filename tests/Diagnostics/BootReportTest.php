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
use Middag\WordPress\Diagnostics\BootReport;
use Middag\WordPress\Diagnostics\CallbackCheck;
use Middag\WordPress\Diagnostics\CheckResult;
use Middag\WordPress\Diagnostics\Enum\CheckScope;
use Middag\WordPress\Diagnostics\Enum\CheckStatus;
use Middag\WordPress\Diagnostics\PackageInspector;
use Middag\WordPress\Diagnostics\PackageProbe;
use Middag\WordPress\Diagnostics\PackageReport;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Tests\Diagnostics\Fixture\DiagnosticsKernel;
use Middag\WordPress\Tests\Diagnostics\Fixture\ThrowingCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BootReport::class)]
#[CoversClass(PackageInspector::class)]
#[CoversClass(PackageReport::class)]
#[CoversClass(PackageProbe::class)]
#[CoversClass(CheckResult::class)]
#[CoversClass(CheckStatus::class)]
final class BootReportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_actions'] = [];
        DiagnosticsKernel::shutdown();
    }

    protected function tearDown(): void
    {
        DiagnosticsKernel::shutdown();
        unset($GLOBALS['__wp_test_actions']);
    }

    #[Test]
    public function aBootedPackageWithAVersionIsHealthy(): void
    {
        DiagnosticsKernel::init();

        $report = BootReport::collect([new PackageProbe('acme-shop', '1.4.0', DiagnosticsKernel::class)]);

        self::assertSame(CheckStatus::Pass, $report->status());
        self::assertSame(BootReport::EXIT_HEALTHY, $report->exitCode());
        self::assertSame([], $report->issues);

        $results = $this->resultsByName($report->packages[0]);
        self::assertSame(CheckStatus::Pass, $results['autoload']->status);
        self::assertStringContainsString('DiagnosticsKernel.php', $results['autoload']->message);
        self::assertSame(CheckStatus::Pass, $results['kernel']->status);
        self::assertSame('1.4.0', $results['version']->message);
    }

    #[Test]
    public function anUnbootedKernelFailsWithoutBootingIt(): void
    {
        $report = BootReport::collect([new PackageProbe('acme-shop', '1.4.0', DiagnosticsKernel::class)]);

        self::assertSame(CheckStatus::Fail, $this->resultsByName($report->packages[0])['kernel']->status);
        self::assertFalse(DiagnosticsKernel::isBooted());
        self::assertSame(BootReport::EXIT_BROKEN, $report->exitCode());
    }

    #[Test]
    public function anUnresolvableEntryClassFailsTheAutoloadCheck(): void
    {
        /** @var class-string $missing */
        $missing = 'Acme\Shop\MissingEntry';

        $report = BootReport::collect([new PackageProbe('acme-shop', '1.0.0', entryClass: $missing)]);

        $autoload = $this->resultsByName($report->packages[0])['autoload'];
        self::assertSame(CheckStatus::Fail, $autoload->status);
        self::assertStringContainsString('does not resolve', $autoload->message);
    }

    #[Test]
    public function theDeclaredAutoloaderPathIsReportedAndAMissingOneFails(): void
    {
        $autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $missing = '/nonexistent/vendor/autoload.php';

        $found = BootReport::collect([new PackageProbe('acme-shop', '1.0.0', entryClass: PackageProbe::class, autoloaderPath: $autoloader)]);
        $absent = BootReport::collect([new PackageProbe('acme-shop', '1.0.0', entryClass: PackageProbe::class, autoloaderPath: $missing)]);

        $foundCheck = $this->resultsByName($found->packages[0])['autoload'];
        self::assertSame(CheckStatus::Pass, $foundCheck->status);
        self::assertStringContainsString('via autoloader ' . $autoloader, $foundCheck->message);

        $absentCheck = $this->resultsByName($absent->packages[0])['autoload'];
        self::assertSame(CheckStatus::Fail, $absentCheck->status);
        self::assertStringContainsString($missing . ' does not exist', $absentCheck->message);
    }

    #[Test]
    public function aKernelClassThatIsNotAKernelFails(): void
    {
        /** @var class-string<DiagnosticsKernel> $notAKernel */
        $notAKernel = WpComponentContext::class;

        $report = BootReport::collect([new PackageProbe('acme-shop', '1.0.0', $notAKernel)]);

        self::assertSame(CheckStatus::Fail, $this->resultsByName($report->packages[0])['kernel']->status);
    }

    #[Test]
    public function aMissingVersionAndEntryClassWarnAndOnlyStrictBreaks(): void
    {
        $report = BootReport::collect([new PackageProbe('acme-shop')]);

        $results = $this->resultsByName($report->packages[0]);
        self::assertSame(CheckStatus::Warn, $results['autoload']->status);
        self::assertSame(CheckStatus::Warn, $results['version']->status);
        self::assertSame(CheckStatus::Pass, $results['kernel']->status);
        self::assertSame(CheckStatus::Warn, $report->status());
        self::assertSame(BootReport::EXIT_HEALTHY, $report->exitCode());
        self::assertSame(BootReport::EXIT_BROKEN, $report->exitCode(strict: true));
    }

    #[Test]
    public function anExpectedPackageThatNeverRegisteredBreaksTheReport(): void
    {
        DiagnosticsKernel::init();

        $report = BootReport::collect(
            [new PackageProbe('acme-shop', '1.0.0', DiagnosticsKernel::class)],
            ['acme-shop', 'acme-quotes'],
        );

        self::assertSame(['expected package "acme-quotes" is not registered (did it load?)'], $report->issues);
        self::assertSame(CheckStatus::Fail, $report->status());
        self::assertSame(BootReport::EXIT_BROKEN, $report->exitCode());
    }

    #[Test]
    public function anEmptyReportIsBroken(): void
    {
        $report = BootReport::collect([]);

        self::assertSame(['no package registered for diagnostics'], $report->issues);
        self::assertSame(BootReport::EXIT_BROKEN, $report->exitCode());
    }

    #[Test]
    public function invalidAndDuplicateRegistrationsAreIssues(): void
    {
        $report = BootReport::collect([new PackageProbe('acme-shop', '1'), new PackageProbe('acme-shop', '1')], [], 2);

        self::assertSame([
            '2 registration(s) ignored: not a package probe',
            'package "acme-shop" registered 2 times',
        ], $report->issues);
    }

    #[Test]
    public function declaredChecksRunAndAThrowingCheckBecomesAFailure(): void
    {
        $probe = new PackageProbe('acme-shop', '1.0.0', checks: [
            new CallbackCheck('schema', CheckScope::Boot, static fn (): bool => true),
            new ThrowingCheck(),
            new CallbackCheck('after', CheckScope::Boot, static fn (): bool => true),
        ]);

        $results = $this->resultsByName(BootReport::collect([$probe])->packages[0]);

        self::assertSame(CheckStatus::Pass, $results['schema']->status);
        self::assertSame(CheckStatus::Fail, $results['admin-shell']->status);
        self::assertSame(CheckScope::Render, $results['admin-shell']->scope);
        self::assertStringContainsString('page has no <head>', $results['admin-shell']->message);
        self::assertSame(CheckStatus::Pass, $results['after']->status, 'a throwing check must not stop the next one');
    }

    #[Test]
    public function coverageStatesBootOnlyUntilARenderCheckRuns(): void
    {
        $bootOnly = BootReport::collect([new PackageProbe('acme-shop', '1')]);
        $withRender = BootReport::collect([new PackageProbe('acme-shop', '1', checks: [
            new CallbackCheck('admin-shell', CheckScope::Render, static fn (): bool => true),
        ])]);

        self::assertFalse($bootOnly->coversRender());
        self::assertSame(['boot'], $bootOnly->toArray()['covers']);
        self::assertTrue($withRender->coversRender());
        self::assertSame(['boot', 'render'], $withRender->toArray()['covers']);
    }

    #[Test]
    public function toArrayCarriesStatusIssuesAndPackageChecks(): void
    {
        $array = BootReport::collect([new PackageProbe('acme-shop', '2.0.0')], ['acme-quotes'])->toArray();

        self::assertSame('fail', $array['status']);
        self::assertCount(1, $array['issues']);
        self::assertSame('acme-shop', $array['packages'][0]['name']);
        self::assertSame('2.0.0', $array['packages'][0]['version']);
        self::assertSame('warn', $array['packages'][0]['status']);
        self::assertSame(['autoload', 'kernel', 'version'], array_column($array['packages'][0]['checks'], 'name'));
    }

    #[Test]
    public function statusOrderingTakesTheWorst(): void
    {
        self::assertSame(CheckStatus::Fail, CheckStatus::Warn->worst(CheckStatus::Fail));
        self::assertSame(CheckStatus::Warn, CheckStatus::Warn->worst(CheckStatus::Pass));
        self::assertSame(CheckStatus::Pass, CheckStatus::Pass->worst(CheckStatus::Pass));
    }

    #[Test]
    public function aProbeNeedsAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PackageProbe('  ');
    }

    /**
     * @return array<string, CheckResult>
     */
    private function resultsByName(PackageReport $package): array
    {
        $byName = [];

        foreach ($package->results as $result) {
            $byName[$result->name] = $result;
        }

        return $byName;
    }
}
