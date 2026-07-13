<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Routing;

use Middag\WordPress\Http\Routing\PublicRouteRegistrar;
use Middag\WordPress\Runtime\WpComponentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PublicRouteRegistrar::class)]
final class PublicRouteRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_rewrite_rules'] = [];
        $GLOBALS['__wp_test_flush_rewrite'] = [];
        $GLOBALS['__wp_test_query_vars'] = [];
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__wp_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_rewrite_rules'],
            $GLOBALS['__wp_test_flush_rewrite'],
            $GLOBALS['__wp_test_query_vars'],
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__wp_test_actions'],
        );
    }

    #[Test]
    public function queryVarDerivesFromComponentNameCollapsingDashes(): void
    {
        self::assertSame('middag_account_route', $this->makeRegistrar('middag-account')->queryVar());
        self::assertSame('acme_route', $this->makeRegistrar('acme')->queryVar());
    }

    #[Test]
    public function registerWiresQueryVarFilterAndTemplateRedirectAction(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->register();

        self::assertArrayHasKey('query_vars', $GLOBALS['__wp_test_filters']);
        self::assertSame(
            [$registrar, 'registerQueryVar'],
            $GLOBALS['__wp_test_filters']['query_vars'][0]['callback'],
        );

        self::assertArrayHasKey('template_redirect', $GLOBALS['__wp_test_actions']);
        self::assertSame(
            [$registrar, 'dispatch'],
            $GLOBALS['__wp_test_actions']['template_redirect'][0]['callback'],
        );
    }

    #[Test]
    public function registerAddsOneRewriteRulePerRoute(): void
    {
        $registrar = $this->makeRegistrar('acme');
        $registrar->addRoute('index', '^acme/?$', static fn (): null => null);
        $registrar->addRoute('show', '^acme/(\d+)/?$', static fn (): null => null);
        $registrar->register();

        self::assertCount(2, $GLOBALS['__wp_test_rewrite_rules']);
        self::assertSame('index.php?acme_route=index', $GLOBALS['__wp_test_rewrite_rules'][0]['query']);
        self::assertSame('^acme/(\d+)/?$', $GLOBALS['__wp_test_rewrite_rules'][1]['regex']);
        self::assertSame('index.php?acme_route=show', $GLOBALS['__wp_test_rewrite_rules'][1]['query']);
    }

    #[Test]
    public function registerQueryVarAppendsTheComponentVar(): void
    {
        $vars = $this->makeRegistrar('acme')->registerQueryVar(['existing_var']);

        self::assertSame(['existing_var', 'acme_route'], $vars);
    }

    #[Test]
    public function dispatchInvokesTheMatchedRouteHandler(): void
    {
        $called = [];
        $registrar = $this->makeRegistrar('acme');
        $registrar->addRoute('show', '^acme/(\d+)/?$', static function () use (&$called): void {
            $called[] = 'show';
        });
        $registrar->register();

        $GLOBALS['__wp_test_query_vars']['acme_route'] = 'show';
        $registrar->dispatch();

        self::assertSame(['show'], $called);
    }

    #[Test]
    public function dispatchDoesNothingWhenNoRouteVarIsPresent(): void
    {
        $called = [];
        $registrar = $this->makeRegistrar('acme');
        $registrar->addRoute('show', '^acme/(\d+)/?$', static function () use (&$called): void {
            $called[] = 'show';
        });

        $registrar->dispatch();

        self::assertSame([], $called);
    }

    #[Test]
    public function dispatchDoesNothingForAnUnknownRouteName(): void
    {
        $called = [];
        $registrar = $this->makeRegistrar('acme');
        $registrar->addRoute('show', '^acme/(\d+)/?$', static function () use (&$called): void {
            $called[] = 'show';
        });

        $GLOBALS['__wp_test_query_vars']['acme_route'] = 'nope';
        $registrar->dispatch();

        self::assertSame([], $called);
    }

    #[Test]
    public function flushRulesTriggersAHardFlush(): void
    {
        $this->makeRegistrar()->flushRules();

        self::assertSame([true], $GLOBALS['__wp_test_flush_rewrite']);
    }

    #[Test]
    public function getRegisteredRoutesMapsNameToRegex(): void
    {
        $registrar = $this->makeRegistrar('acme');
        $registrar->addRoute('index', '^acme/?$', static fn (): null => null);
        $registrar->addRoute('show', '^acme/(\d+)/?$', static fn (): null => null);

        self::assertSame(
            ['index' => '^acme/?$', 'show' => '^acme/(\d+)/?$'],
            $registrar->getRegisteredRoutes(),
        );
    }

    #[Test]
    public function differentComponentsProduceDistinctQueryVars(): void
    {
        self::assertNotSame(
            $this->makeRegistrar('acme')->queryVar(),
            $this->makeRegistrar('globex')->queryVar(),
        );
    }

    private function makeRegistrar(string $component = 'acme'): PublicRouteRegistrar
    {
        return new PublicRouteRegistrar(new WpComponentContext($component, '5.0.0'));
    }
}
