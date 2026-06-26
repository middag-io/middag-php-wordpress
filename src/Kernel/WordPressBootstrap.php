<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Kernel;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\HookfileLoaderInterface;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\WordPress\Config\WpConfigResolver;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Database\WpdbSqlDialect;
use Middag\WordPress\Infrastructure\Bus\WpUserContext;
use Middag\WordPress\Kernel\Loader\WordPressHookfileLoader;
use Middag\WordPress\Translation\WpTranslator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use wpdb;

/**
 * WordPress platform bootstrap.
 *
 * Configures the DI container with WordPress-specific synthetic services and the
 * platform adapter bindings for the framework's host-bridge contracts. Called by
 * the framework ContainerFactory during init.
 *
 * Scope is the minimum-viable OSS adapter surface: config, user context,
 * translation, maintenance gate, connection/dialect, and the native hookfile
 * loader. Governed concerns (signal/outbox, async job dispatch, Item EAV) are
 * NOT wired here — they live in a non-OSS MIDDAG layer, never in the OSS adapter.
 */
final class WordPressBootstrap implements BootstrapInterface
{
    public function configure(ContainerBuilder $builder): void
    {
        // Synthetic services — injected at runtime from WordPress globals.
        $builder->register('wordpress.wpdb', wpdb::class)->setSynthetic(true);

        // Platform adapters → framework host-bridge contracts.
        $builder->register(ConfigResolverInterface::class, WpConfigResolver::class)->setPublic(true);
        $builder->register(UserContextResolverInterface::class, WpUserContext::class)->setPublic(true);
        $builder->register(TranslatorInterface::class, WpTranslator::class)->setPublic(true);
        $builder->register(MaintenanceGateInterface::class, WpMaintenanceGate::class)->setPublic(true);

        // Database seam — $wpdb behind ConnectionAdapterInterface + dialect.
        // The adapter self-builds its prefixed dialect from $wpdb->prefix at
        // runtime; SqlDialectInterface resolves to that same instance so callers
        // share the correct (prefixed) dialect rather than a prefix-less default.
        $builder->register(ConnectionAdapterInterface::class, WpdbConnectionAdapter::class)
            ->setArguments([new Reference('wordpress.wpdb')])
            ->setPublic(true);
        $builder->register(SqlDialectInterface::class, WpdbSqlDialect::class)
            ->setFactory([new Reference(ConnectionAdapterInterface::class), 'dialect'])
            ->setPublic(true);

        // Native hookfile loader (discovers + loads middag_hooks.php across
        // content/theme/plugins).
        $builder->register(HookfileLoaderInterface::class, WordPressHookfileLoader::class)
            ->setPublic(true);
    }

    public function platform(): string
    {
        return 'wordpress';
    }

    public function getProjectRoot(): string
    {
        return '';
    }

    public function getOptions(): array
    {
        return [];
    }
}
