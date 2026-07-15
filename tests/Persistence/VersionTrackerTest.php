<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Persistence;

use Middag\Framework\Database\Contract\VersionTrackerInterface;
use Middag\WordPress\Persistence\VersionTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VersionTracker::class)]
final class VersionTrackerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_options'] = [];
        $GLOBALS['__wp_test_option_autoload'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_options'], $GLOBALS['__wp_test_option_autoload']);
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(VersionTrackerInterface::class, new VersionTracker('middag_core_schema_version'));
    }

    #[Test]
    public function getVersionReturnsZeroWhenNotYetInstalled(): void
    {
        self::assertSame(0, (new VersionTracker('middag_core_schema_version'))->getVersion());
    }

    #[Test]
    public function setVersionRoundTripsThroughTheOption(): void
    {
        $tracker = new VersionTracker('middag_core_schema_version');
        $tracker->setVersion(2026071500);

        self::assertSame(2026071500, $tracker->getVersion());
        self::assertSame(2026071500, $GLOBALS['__wp_test_options']['middag_core_schema_version']);
    }

    #[Test]
    public function setVersionWritesTheOptionWithoutAutoload(): void
    {
        (new VersionTracker('middag_core_schema_version'))->setVersion(1);

        self::assertFalse($GLOBALS['__wp_test_option_autoload']['middag_core_schema_version']);
    }

    #[Test]
    public function getVersionCoercesStoredStringsToInt(): void
    {
        $GLOBALS['__wp_test_options']['middag_core_schema_version'] = '42';

        self::assertSame(42, (new VersionTracker('middag_core_schema_version'))->getVersion());
    }

    #[Test]
    public function trackersWithDistinctOptionNamesAreIndependent(): void
    {
        $core = new VersionTracker('middag_core_schema_version');
        $framework = new VersionTracker('middag_framework_schema_version');

        $core->setVersion(7);

        self::assertSame(7, $core->getVersion());
        self::assertSame(0, $framework->getVersion());
    }
}
