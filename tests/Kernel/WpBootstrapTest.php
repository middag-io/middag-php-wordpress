<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Kernel;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Framework\Kernel\Contract\HookfileLoaderInterface;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\WordPress\Bus\WpUserContext;
use Middag\WordPress\Config\WpConfigResolver;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Database\WpdbSqlDialect;
use Middag\WordPress\Kernel\Loader\WpHookfileLoader;
use Middag\WordPress\Kernel\WpBootstrap;
use Middag\WordPress\Kernel\WpMaintenanceGate;
use Middag\WordPress\Translation\WpTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use wpdb;

/**
 * @internal
 */
#[CoversClass(WpBootstrap::class)]
final class WpBootstrapTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        (new WpBootstrap())->configure($this->builder);
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(BootstrapInterface::class, new WpBootstrap());
    }

    #[Test]
    public function registersTheSyntheticWpdbService(): void
    {
        self::assertTrue($this->builder->hasDefinition('wordpress.wpdb'));

        $definition = $this->builder->getDefinition('wordpress.wpdb');
        self::assertSame(wpdb::class, $definition->getClass());
        self::assertTrue($definition->isSynthetic());
    }

    #[Test]
    public function bindsTheConfigResolverContractPublicly(): void
    {
        $definition = $this->builder->getDefinition(ConfigResolverInterface::class);

        self::assertSame(WpConfigResolver::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function bindsTheUserContextResolverContractPublicly(): void
    {
        $definition = $this->builder->getDefinition(UserContextResolverInterface::class);

        self::assertSame(WpUserContext::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function bindsTheTranslatorContractPublicly(): void
    {
        $definition = $this->builder->getDefinition(TranslatorInterface::class);

        self::assertSame(WpTranslator::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function bindsTheMaintenanceGateContractPublicly(): void
    {
        $definition = $this->builder->getDefinition(MaintenanceGateInterface::class);

        self::assertSame(WpMaintenanceGate::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function bindsTheConnectionAdapterWithTheWpdbReference(): void
    {
        $definition = $this->builder->getDefinition(ConnectionAdapterInterface::class);

        self::assertSame(WpdbConnectionAdapter::class, $definition->getClass());
        self::assertTrue($definition->isPublic());

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame('wordpress.wpdb', (string) $arguments[0]);
    }

    #[Test]
    public function bindsTheSqlDialectViaTheConnectionAdapterFactory(): void
    {
        $definition = $this->builder->getDefinition(SqlDialectInterface::class);

        self::assertSame(WpdbSqlDialect::class, $definition->getClass());
        self::assertTrue($definition->isPublic());

        $factory = $definition->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(ConnectionAdapterInterface::class, (string) $factory[0]);
        self::assertSame('dialect', $factory[1]);
    }

    #[Test]
    public function bindsTheHookfileLoaderContractPublicly(): void
    {
        $definition = $this->builder->getDefinition(HookfileLoaderInterface::class);

        self::assertSame(WpHookfileLoader::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function platformIdentifierIsWordpress(): void
    {
        self::assertSame('wordpress', (new WpBootstrap())->platform());
    }

    #[Test]
    public function projectRootIsEmptyForThisAdapter(): void
    {
        self::assertSame('', (new WpBootstrap())->getProjectRoot());
    }

    #[Test]
    public function optionsAreEmptyForThisAdapter(): void
    {
        self::assertSame([], (new WpBootstrap())->getOptions());
    }
}
