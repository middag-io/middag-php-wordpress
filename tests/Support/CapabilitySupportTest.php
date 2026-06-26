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

use Middag\WordPress\Support\CapabilitySupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Role;

/**
 * @internal
 */
#[CoversClass(CapabilitySupport::class)]
final class CapabilitySupportTest extends TestCase
{
    protected function setUp(): void
    {
        // Seed a single existing role with no capabilities; tests mutate it.
        $GLOBALS['__wp_test_roles'] = [
            'editor' => new WP_Role('editor', []),
        ];
        $GLOBALS['__wp_test_caps'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_roles'], $GLOBALS['__wp_test_caps']);
    }

    // ─── grant ──────────────────────────────────────────────────────────────────

    #[Test]
    public function addCapGrantsTheCapabilityOnTheRole(): void
    {
        $result = CapabilitySupport::addCap('editor', 'middag_manage_things');

        self::assertTrue($result);
        $role = $GLOBALS['__wp_test_roles']['editor'];
        self::assertArrayHasKey('middag_manage_things', $role->capabilities);
        self::assertTrue($role->capabilities['middag_manage_things']);
    }

    #[Test]
    public function addCapHonoursAnExplicitDenyGrant(): void
    {
        CapabilitySupport::addCap('editor', 'middag_manage_things', false);

        $role = $GLOBALS['__wp_test_roles']['editor'];
        self::assertFalse($role->capabilities['middag_manage_things']);
    }

    #[Test]
    public function addCapReturnsFalseForAnUnknownRole(): void
    {
        self::assertFalse(CapabilitySupport::addCap('ghost', 'middag_manage_things'));
    }

    // ─── revoke ─────────────────────────────────────────────────────────────────

    #[Test]
    public function removeCapRevokesTheCapabilityFromTheRole(): void
    {
        CapabilitySupport::addCap('editor', 'middag_manage_things');

        $result = CapabilitySupport::removeCap('editor', 'middag_manage_things');

        self::assertTrue($result);
        $role = $GLOBALS['__wp_test_roles']['editor'];
        self::assertArrayNotHasKey('middag_manage_things', $role->capabilities);
    }

    #[Test]
    public function removeCapReturnsFalseForAnUnknownRole(): void
    {
        self::assertFalse(CapabilitySupport::removeCap('ghost', 'middag_manage_things'));
    }

    // ─── roles (the activation/lifecycle install path) ───────────────────────────

    #[Test]
    public function addRoleRegistersANewRoleWithItsCapabilities(): void
    {
        $role = CapabilitySupport::addRole('middag_operator', 'Operator', [
            'read' => true,
            'middag_manage_things' => true,
        ]);

        self::assertInstanceOf(WP_Role::class, $role);
        self::assertArrayHasKey('middag_operator', $GLOBALS['__wp_test_roles']);
        self::assertTrue($role->capabilities['middag_manage_things']);
    }

    #[Test]
    public function addRoleReturnsNullWhenTheRoleAlreadyExists(): void
    {
        self::assertNull(CapabilitySupport::addRole('editor', 'Editor'));
    }

    #[Test]
    public function removeRoleDeletesTheRole(): void
    {
        CapabilitySupport::removeRole('editor');

        self::assertArrayNotHasKey('editor', $GLOBALS['__wp_test_roles']);
        self::assertFalse(CapabilitySupport::roleExists('editor'));
    }

    #[Test]
    public function roleExistsReflectsTheRegistry(): void
    {
        self::assertTrue(CapabilitySupport::roleExists('editor'));
        self::assertFalse(CapabilitySupport::roleExists('ghost'));
    }

    /**
     * Proves the activation-install path: register a role + grant a caller-supplied
     * cap with no duplicated logic, then confirm the cap is installed on the role.
     */
    #[Test]
    public function activationInstallPathRegistersRoleThenGrantsCaps(): void
    {
        CapabilitySupport::addRole('middag_operator', 'Operator');
        $granted = CapabilitySupport::addCap('middag_operator', 'middag_manage_things');

        self::assertTrue($granted);
        $role = $GLOBALS['__wp_test_roles']['middag_operator'];
        self::assertTrue($role->capabilities['middag_manage_things']);
    }

    // ─── current-user read path ───────────────────────────────────────────────────

    #[Test]
    public function userCanReflectsTheCurrentUserCapabilityMap(): void
    {
        $GLOBALS['__wp_test_caps']['middag_manage_things'] = true;

        self::assertTrue(CapabilitySupport::userCan('middag_manage_things'));
        self::assertFalse(CapabilitySupport::userCan('middag_other'));
    }
}
