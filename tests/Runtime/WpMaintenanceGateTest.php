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

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Middag\WordPress\Runtime\WpMaintenanceGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpMaintenanceGate::class)]
final class WpMaintenanceGateTest extends TestCase
{
    /** Absolute path to the legacy `.maintenance` drop-in under the stubbed ABSPATH. */
    private string $maintenanceFile;

    protected function setUp(): void
    {
        // ABSPATH is defined unconditionally by tests/stubs/wp-stubs.php.
        if (!is_dir(ABSPATH)) {
            mkdir(ABSPATH, 0o777, true);
        }

        $this->maintenanceFile = ABSPATH . '.maintenance';
        $this->removeMaintenanceFile();
    }

    protected function tearDown(): void
    {
        $this->removeMaintenanceFile();
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(MaintenanceGateInterface::class, new WpMaintenanceGate());
    }

    #[Test]
    public function isNotUnderMaintenanceWhenNoDropInFileExists(): void
    {
        // No `wp_is_maintenance_mode()` (unstubbed) and no drop-in file → not gated.
        self::assertFalse((new WpMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function isNotUnderMaintenanceWhenDropInHasNoUpgradingTimestamp(): void
    {
        $this->writeMaintenanceFile("<?php\n// no upgrading assignment here\n");

        self::assertFalse((new WpMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function isUnderMaintenanceWhenDropInTimestampIsRecent(): void
    {
        $this->writeMaintenanceFile('<?php $upgrading = ' . time() . ';');

        self::assertTrue((new WpMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function isNotUnderMaintenanceWhenDropInTimestampIsStale(): void
    {
        // Older than the 600s window core (and this gate) considers active.
        $this->writeMaintenanceFile('<?php $upgrading = ' . (time() - 601) . ';');

        self::assertFalse((new WpMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function isNotUnderMaintenanceWhenDropInTimestampIsZero(): void
    {
        // A zero timestamp matches the regex but fails the `> 0` guard.
        $this->writeMaintenanceFile('<?php $upgrading = 0;');

        self::assertFalse((new WpMaintenanceGate())->isUnderMaintenance());
    }

    private function writeMaintenanceFile(string $contents): void
    {
        file_put_contents($this->maintenanceFile, $contents);
    }

    private function removeMaintenanceFile(): void
    {
        if (is_file($this->maintenanceFile)) {
            unlink($this->maintenanceFile);
        }
    }
}
