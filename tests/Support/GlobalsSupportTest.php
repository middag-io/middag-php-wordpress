<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Support;

use Middag\WordPress\Support\GlobalsSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use wpdb;

/**
 * @internal
 */
#[CoversClass(GlobalsSupport::class)]
final class GlobalsSupportTest extends TestCase
{
    /** @var mixed Snapshot of the real global so the suite stays isolated. */
    private mixed $previousWpdb = null;

    private bool $hadWpdb = false;

    protected function setUp(): void
    {
        $this->hadWpdb = array_key_exists('wpdb', $GLOBALS);
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        unset($GLOBALS['wpdb']);
    }

    protected function tearDown(): void
    {
        if ($this->hadWpdb) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
    }

    #[Test]
    public function returnsNullWhenTheGlobalIsAbsent(): void
    {
        self::assertNull(GlobalsSupport::wpdb());
    }

    #[Test]
    public function returnsNullWhenTheGlobalIsNotAWpdbInstance(): void
    {
        $GLOBALS['wpdb'] = 'not-a-wpdb';

        self::assertNull(GlobalsSupport::wpdb());
    }

    #[Test]
    public function returnsTheWpdbInstanceWhenInitialized(): void
    {
        $wpdb = new wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        self::assertSame($wpdb, GlobalsSupport::wpdb());
    }
}
