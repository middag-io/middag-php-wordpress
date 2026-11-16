<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Integration\Diagnostics;

use Middag\WordPress\Diagnostics\BootReport;
use Middag\WordPress\Diagnostics\DiagnosticsRegistry;
use Middag\WordPress\Diagnostics\Enum\CheckStatus;
use Middag\WordPress\Diagnostics\PackageProbe;
use Middag\WordPress\Http\WpRestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The diagnostics registry on the real WordPress Plugin API: packages declare
 * themselves on a WordPress filter and the boot report reads them back.
 *
 * @internal
 */
#[CoversClass(DiagnosticsRegistry::class)]
#[CoversClass(BootReport::class)]
final class DiagnosticsRegistryIntegrationTest extends TestCase
{
    private const string FILTER = 'middaglib_it_diagnostics';

    protected function tearDown(): void
    {
        remove_all_filters(self::FILTER);
        parent::tearDown();
    }

    #[Test]
    public function packagesRegisteredByTwoPluginsShareOneHealthyReport(): void
    {
        (new DiagnosticsRegistry(self::FILTER))->register(
            new PackageProbe('acme-shop', '1.2.0', entryClass: WpRestKernel::class),
        );
        add_filter(self::FILTER, static fn (array $probes): array => [
            ...$probes,
            new PackageProbe('acme-addon', '0.3.0', entryClass: DiagnosticsRegistry::class),
        ]);

        $report = (new DiagnosticsRegistry(self::FILTER))->report(['acme-shop', 'acme-addon']);

        self::assertTrue(has_filter(self::FILTER));
        self::assertSame(CheckStatus::Pass, $report->status());
        self::assertSame(BootReport::EXIT_HEALTHY, $report->exitCode(strict: true));
        self::assertSame(
            ['acme-shop', 'acme-addon'],
            array_column($report->toArray()['packages'], 'name'),
        );
    }

    #[Test]
    public function aMissingExpectedPackageOrAnUnresolvedClassBreaksTheReport(): void
    {
        (new DiagnosticsRegistry(self::FILTER))->register(
            new PackageProbe('acme-shop', '1.2.0', entryClass: 'Acme\Shop\NeverAutoloaded'),
        );

        $registry = new DiagnosticsRegistry(self::FILTER);

        self::assertSame(BootReport::EXIT_BROKEN, $registry->report()->exitCode());
        self::assertSame(CheckStatus::Fail, $registry->report(['acme-shop', 'acme-addon'])->status());
    }
}
