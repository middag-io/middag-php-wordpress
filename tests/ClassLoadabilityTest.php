<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests;

use Middag\WordPress\Bus\WpUserContext;
use Middag\WordPress\Config\WpConfigResolver;
use Middag\WordPress\Cron\CronHandler;
use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Database\WpdbSqlDialect;
use Middag\WordPress\Domain\Comment\CommentDto;
use Middag\WordPress\Domain\Media\MediaAttachmentDto;
use Middag\WordPress\Domain\Post\PostMetaRepository;
use Middag\WordPress\Domain\Post\PostRepository;
use Middag\WordPress\Domain\Taxonomy\TaxonomyDto;
use Middag\WordPress\Domain\Taxonomy\TermDto;
use Middag\WordPress\Domain\WooCommerce\OrderReference;
use Middag\WordPress\Domain\WooCommerce\ProductReference;
use Middag\WordPress\Domain\WooCommerce\WooCommerceAvailability;
use Middag\WordPress\Exception\HookRegistrationException;
use Middag\WordPress\Exception\SettingsRenderException;
use Middag\WordPress\Exception\WordPressAdapterException;
use Middag\WordPress\Exception\WordPressDatabaseException;
use Middag\WordPress\Hook\Contract\HookInterface;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Http\Contract\RestControllerInterface;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Http\Middleware\AuthMiddleware;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WordPress\Mail\EmailSender;
use Middag\WordPress\Mail\EmailTemplate;
use Middag\WordPress\Persistence\QueryBuilder;
use Middag\WordPress\Privacy\Contract\PersonalDataProviderInterface;
use Middag\WordPress\Privacy\PrivacyRegistrar;
use Middag\WordPress\Runtime\PluginLifecycle;
use Middag\WordPress\Runtime\WpBootstrap;
use Middag\WordPress\Runtime\WpMaintenanceGate;
use Middag\WordPress\Settings\SettingDefinition;
use Middag\WordPress\Settings\SettingsRegistrar;
use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test: verifies all WP adapter classes can be loaded without WordPress runtime.
 * Classes that reference WP functions in their body (not constructor) should still load.
 *
 * @internal
 */
#[CoversNothing]
final class ClassLoadabilityTest extends TestCase
{
    #[Test]
    #[DataProvider('classProvider')]
    public function classIsLoadable(string $className): void
    {
        $this->assertTrue(
            class_exists($className) || interface_exists($className),
            sprintf('Class %s should be loadable via autoloader', $className)
        );
    }

    /** @return array<string, array{string}> */
    public static function classProvider(): array
    {
        return [
            'QueryBuilder' => [QueryBuilder::class],
            'PostMetaRepository' => [PostMetaRepository::class],
            'PostRepository' => [PostRepository::class],
            'RestControllerInterface' => [RestControllerInterface::class],
            'RestResponse' => [RestResponse::class],
            'HookInterface' => [HookInterface::class],
            'HookRegistrar' => [HookRegistrar::class],
            'InertiaAdapter' => [InertiaAdapter::class],
            'WpConfigResolver' => [WpConfigResolver::class],
            'CronHandler' => [CronHandler::class],
            'CronRegistrar' => [CronRegistrar::class],
            'PluginLifecycle' => [PluginLifecycle::class],
            'EmailSender' => [EmailSender::class],
            'EmailTemplate' => [EmailTemplate::class],
            'AuthMiddleware' => [AuthMiddleware::class],
            'WpBootstrap' => [WpBootstrap::class],
            'WpUserContext' => [WpUserContext::class],
            'WpdbConnectionAdapter' => [WpdbConnectionAdapter::class],
            'WpdbSqlDialect' => [WpdbSqlDialect::class],
            'CommentDto' => [CommentDto::class],
            'MediaAttachmentDto' => [MediaAttachmentDto::class],
            'TaxonomyDto' => [TaxonomyDto::class],
            'TermDto' => [TermDto::class],
            'ProductReference' => [ProductReference::class],
            'OrderReference' => [OrderReference::class],
            'WooCommerceAvailability' => [WooCommerceAvailability::class],
            'WordPressAdapterException' => [WordPressAdapterException::class],
            'HookRegistrationException' => [HookRegistrationException::class],
            'SettingsRenderException' => [SettingsRenderException::class],
            'WordPressDatabaseException' => [WordPressDatabaseException::class],
            'WpMaintenanceGate' => [WpMaintenanceGate::class],
            'WpTranslator' => [WpTranslator::class],
            'SettingDefinition' => [SettingDefinition::class],
            'SettingsRegistrar' => [SettingsRegistrar::class],
            'PersonalDataProviderInterface' => [PersonalDataProviderInterface::class],
            'PrivacyRegistrar' => [PrivacyRegistrar::class],
        ];
    }
}
