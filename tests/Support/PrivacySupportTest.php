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

use Middag\WordPress\Support\PrivacySupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrivacySupport::class)]
final class PrivacySupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__wp_test_privacy_policy_content'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__wp_test_privacy_policy_content'],
        );
    }

    #[Test]
    public function registerExportersDelegatesToTheExportersFilter(): void
    {
        $callback = static fn (array $e): array => $e;

        PrivacySupport::registerExporters($callback, 20);

        $registered = $GLOBALS['__wp_test_filters']['wp_privacy_personal_data_exporters'][0] ?? null;
        self::assertNotNull($registered, 'the exporters filter was not registered');
        self::assertSame($callback, $registered['callback']);
        self::assertSame(20, $registered['priority']);
        self::assertSame(1, $registered['accepted_args']);
    }

    #[Test]
    public function registerErasersDelegatesToTheErasersFilter(): void
    {
        $callback = static fn (array $e): array => $e;

        PrivacySupport::registerErasers($callback);

        $registered = $GLOBALS['__wp_test_filters']['wp_privacy_personal_data_erasers'][0] ?? null;
        self::assertNotNull($registered, 'the erasers filter was not registered');
        self::assertSame($callback, $registered['callback']);
        self::assertSame(10, $registered['priority']);
    }

    #[Test]
    public function addPrivacyPolicyContentDelegatesToWordPress(): void
    {
        PrivacySupport::addPrivacyPolicyContent('My Plugin', '<p>We store orders.</p>');

        $recorded = $GLOBALS['__wp_test_privacy_policy_content'][0] ?? null;
        self::assertNotNull($recorded, 'the privacy policy content was not recorded');
        self::assertSame('My Plugin', $recorded['plugin_name']);
        self::assertSame('<p>We store orders.</p>', $recorded['policy_text']);
    }
}
