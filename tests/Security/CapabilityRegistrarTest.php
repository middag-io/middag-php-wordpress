<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Security;

use Middag\WordPress\Security\CapabilityRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Role;

/**
 * @internal
 */
#[CoversClass(CapabilityRegistrar::class)]
final class CapabilityRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_roles'] = [
            'administrator' => new WP_Role('administrator'),
            'shop_manager' => new WP_Role('shop_manager'),
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_roles']);
    }

    #[Test]
    public function registerGrantsEveryDeclaredCapabilityPerRole(): void
    {
        $registrar = new CapabilityRegistrar([
            'administrator' => ['middag_manage', 'middag_view'],
            'shop_manager' => ['middag_view'],
        ]);

        $results = $registrar->register();

        self::assertSame([
            'administrator:middag_manage' => true,
            'administrator:middag_view' => true,
            'shop_manager:middag_view' => true,
        ], $results);
        self::assertArrayHasKey('middag_manage', $GLOBALS['__wp_test_roles']['administrator']->capabilities);
        self::assertArrayHasKey('middag_view', $GLOBALS['__wp_test_roles']['shop_manager']->capabilities);
    }

    #[Test]
    public function unregisterRevokesWhatRegisterGranted(): void
    {
        $registrar = new CapabilityRegistrar(['administrator' => ['middag_manage']]);
        $registrar->register();

        $results = $registrar->unregister();

        self::assertSame(['administrator:middag_manage' => true], $results);
        self::assertArrayNotHasKey('middag_manage', $GLOBALS['__wp_test_roles']['administrator']->capabilities);
    }

    #[Test]
    public function missingRoleIsReportedNotFatal(): void
    {
        $registrar = new CapabilityRegistrar(['editor' => ['middag_view']]);

        self::assertSame(['editor:middag_view' => false], $registrar->register());
    }
}
