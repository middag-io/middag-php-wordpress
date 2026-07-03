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

use Middag\WordPress\Support\AdminSupport;
use Middag\WordPress\Support\MetaSupport;
use Middag\WordPress\Support\UploadSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MetaSupport::class)]
#[CoversClass(AdminSupport::class)]
#[CoversClass(UploadSupport::class)]
final class MetaAdminUploadSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_metadata'] = [];
        $GLOBALS['__wp_test_admin_menus'] = [];
        $GLOBALS['__wp_test_admin_submenus'] = [];
        $GLOBALS['__wp_test_actions'] = [];
        $GLOBALS['__wp_test_upload_dir'] = [
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.test/uploads',
        ];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_metadata'],
            $GLOBALS['__wp_test_admin_menus'],
            $GLOBALS['__wp_test_admin_submenus'],
            $GLOBALS['__wp_test_actions'],
            $GLOBALS['__wp_test_upload_dir'],
        );
    }

    #[Test]
    public function metadataRoundTripForAnyMetaType(): void
    {
        self::assertTrue(MetaSupport::update('term', 42, 'acme_color', 'blue'));
        self::assertSame('blue', MetaSupport::get('term', 42, 'acme_color'));
        self::assertTrue(MetaSupport::delete('term', 42, 'acme_color'));
        self::assertSame('', MetaSupport::get('term', 42, 'acme_color'));
    }

    #[Test]
    public function adminMenuAndSubmenuRegister(): void
    {
        $hook = AdminSupport::addMenuPage('Acme', 'Acme', 'manage_options', 'acme', static fn (): string => '');
        AdminSupport::addSubmenuPage('acme', 'Settings', 'Settings', 'manage_options', 'acme-settings', static fn (): string => '');

        self::assertSame('toplevel_page_acme', $hook);
        self::assertArrayHasKey('acme', $GLOBALS['__wp_test_admin_menus']);
        self::assertSame('acme', $GLOBALS['__wp_test_admin_submenus']['acme-settings']['parent_slug']);
    }

    #[Test]
    public function noticeQueuesAnEscapedAdminNoticesCallback(): void
    {
        AdminSupport::notice('Saved <ok>', 'success');

        $callbacks = $GLOBALS['__wp_test_actions']['admin_notices'] ?? [];
        self::assertCount(1, $callbacks);

        ob_start();
        $callbacks[0]['callback']();
        $html = ob_get_clean();

        self::assertStringContainsString('notice-success', $html);
        self::assertStringContainsString('Saved &lt;ok&gt;', $html);
    }

    #[Test]
    public function uploadPathsComeFromWpUploadDir(): void
    {
        self::assertSame('/tmp/uploads', UploadSupport::baseDir());
        self::assertSame('http://example.test/uploads', UploadSupport::baseUrl());
    }
}
