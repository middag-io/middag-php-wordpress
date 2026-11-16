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
use Middag\WordPress\Diagnostics\PackageProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The test stubs do not round-trip filters (add_filter records into
 * $__wp_test_filters, apply_filters reads $__middag_test_wp_filters), so each
 * side is exercised on its own and the recorded callback is replayed by hand.
 *
 * @internal
 */
#[CoversClass(DiagnosticsRegistry::class)]
final class DiagnosticsRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__middag_test_wp_filters'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_filters'], $GLOBALS['__middag_test_wp_filters']);
    }

    #[Test]
    public function defaultsToTheNeutralFilterName(): void
    {
        self::assertSame('middaglib_diagnostics', (new DiagnosticsRegistry())->filterName());
    }

    #[Test]
    public function registerAppendsTheProbeOnTheConfiguredFilter(): void
    {
        $first = new PackageProbe('acme-shop', '1.0.0');
        $second = new PackageProbe('acme-quotes', '2.0.0');

        (new DiagnosticsRegistry('acme_diagnostics'))->register($second);

        $registered = $GLOBALS['__wp_test_filters']['acme_diagnostics'] ?? [];
        self::assertCount(1, $registered);
        self::assertSame([$first, $second], ($registered[0]['callback'])([$first]));
        self::assertSame([$second], ($registered[0]['callback'])('not an array'));
    }

    #[Test]
    public function collectKeepsProbesAndCountsInvalidEntries(): void
    {
        $probe = new PackageProbe('acme-shop', '1.0.0');
        $GLOBALS['__middag_test_wp_filters']['acme_diagnostics'] = static fn (array $value): array => [...$value, $probe, new stdClass(), 'acme'];

        $collected = (new DiagnosticsRegistry('acme_diagnostics'))->collect();

        self::assertSame([$probe], $collected['probes']);
        self::assertSame(2, $collected['invalid']);
    }

    #[Test]
    public function aFilterReturningANonArrayIsOneInvalidEntry(): void
    {
        $GLOBALS['__middag_test_wp_filters']['acme_diagnostics'] = static fn (): string => 'broken';

        self::assertSame(['probes' => [], 'invalid' => 1], (new DiagnosticsRegistry('acme_diagnostics'))->collect());
    }

    #[Test]
    public function reportFeedsTheExpectedNames(): void
    {
        $GLOBALS['__middag_test_wp_filters']['acme_diagnostics'] = static fn (array $value): array => [...$value, new PackageProbe('acme-shop', '1.0.0')];

        $report = (new DiagnosticsRegistry('acme_diagnostics'))->report(['acme-quotes']);

        self::assertSame('acme-shop', $report->packages[0]->name);
        self::assertSame(['expected package "acme-quotes" is not registered (did it load?)'], $report->issues);
    }

    #[Test]
    public function anEmptyFilterNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DiagnosticsRegistry(' ');
    }
}
