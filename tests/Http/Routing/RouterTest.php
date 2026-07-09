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

use Middag\WordPress\Http\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    #[Test]
    public function patchRegistersARouteResolvableByThePatchVerb(): void
    {
        $router = new Router();
        $router->patch('/widgets/{id}', ['WidgetController', 'update']);

        $match = $router->resolve('PATCH', '/widgets/42');

        self::assertNotNull($match);
        self::assertSame('WidgetController', $match['controller']);
        self::assertSame('update', $match['method']);
        self::assertSame('42', $match['params']['id']);
    }

    #[Test]
    public function aPatchRouteDoesNotLeakIntoOtherVerbs(): void
    {
        $router = new Router();
        $router->patch('/widgets/{id}', ['WidgetController', 'update']);

        self::assertNull($router->resolve('GET', '/widgets/42'));
        self::assertNull($router->resolve('POST', '/widgets/42'));
    }

    #[Test]
    public function aRegisteredVerbWithNoMatchingPatternResolvesToNull(): void
    {
        $router = new Router();
        $router->get('/widgets/{id}', ['WidgetController', 'show']);

        self::assertNull($router->resolve('GET', '/totally/different/path'));
    }

    #[Test]
    public function getRegistersARouteResolvableByTheGetVerb(): void
    {
        $router = new Router();
        $router->get('/widgets/{id}', ['WidgetController', 'show']);

        $match = $router->resolve('GET', '/widgets/42');

        self::assertNotNull($match);
        self::assertSame('show', $match['method']);
        self::assertSame('42', $match['params']['id']);
    }

    #[Test]
    public function postRegistersARouteResolvableByThePostVerb(): void
    {
        $router = new Router();
        $router->post('/widgets', ['WidgetController', 'store']);

        $match = $router->resolve('POST', '/widgets');

        self::assertNotNull($match);
        self::assertSame('store', $match['method']);
    }

    #[Test]
    public function putRegistersARouteResolvableByThePutVerb(): void
    {
        $router = new Router();
        $router->put('/widgets/{id}', ['WidgetController', 'replace']);

        $match = $router->resolve('PUT', '/widgets/42');

        self::assertNotNull($match);
        self::assertSame('replace', $match['method']);
        self::assertSame('42', $match['params']['id']);
    }

    #[Test]
    public function deleteRegistersARouteResolvableByTheDeleteVerb(): void
    {
        $router = new Router();
        $router->delete('/widgets/{id}', ['WidgetController', 'destroy']);

        $match = $router->resolve('DELETE', '/widgets/42');

        self::assertNotNull($match);
        self::assertSame('destroy', $match['method']);
        self::assertSame('42', $match['params']['id']);
    }
}
