<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http;

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\WordPress\Http\WpHttpKernel;
use Middag\WordPress\Security\Attribute\Nonce;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * @internal
 */
#[CoversClass(WpHttpKernel::class)]
#[CoversClass(Nonce::class)]
final class WpHttpKernelTest extends TestCase
{
    protected function setUp(): void
    {
        $_REQUEST = [];
        unset($_SERVER['HTTP_X_WP_NONCE']);
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        unset($_SERVER['HTTP_X_WP_NONCE']);
    }

    #[Test]
    public function aValidNoncePassesTheGate(): void
    {
        $_REQUEST['_wpnonce'] = 'nonce-do-thing';

        $this->applyPlatformAuth(new FixtureNonceController(), 'guarded');

        $this->addToAssertionCount(1); // no exception
    }

    #[Test]
    public function aMissingNonceIsRejected(): void
    {
        $this->expectException(MiddagAuthorizationException::class);

        $this->applyPlatformAuth(new FixtureNonceController(), 'guarded');
    }

    #[Test]
    public function anInvalidNonceIsRejected(): void
    {
        $_REQUEST['_wpnonce'] = 'forged';

        $this->expectException(MiddagAuthorizationException::class);

        $this->applyPlatformAuth(new FixtureNonceController(), 'guarded');
    }

    #[Test]
    public function theHeaderFallbackIsAccepted(): void
    {
        $_SERVER['HTTP_X_WP_NONCE'] = 'nonce-do-thing';

        $this->applyPlatformAuth(new FixtureNonceController(), 'guarded');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function aCustomParamNameIsResolved(): void
    {
        $_REQUEST['custom_nonce'] = 'nonce-custom-action';

        $this->applyPlatformAuth(new FixtureNonceController(), 'customParam');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function anUnguardedActionIsANoOp(): void
    {
        $this->applyPlatformAuth(new FixtureNonceController(), 'open');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function aNonRequiredNonceIsANoOp(): void
    {
        $this->applyPlatformAuth(new FixtureNonceController(), 'optional');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function theClassLevelAttributeGuardsEveryAction(): void
    {
        $this->expectException(MiddagAuthorizationException::class);

        $this->applyPlatformAuth(new FixtureClassNonceController(), 'anything');
    }

    private function applyPlatformAuth(ControllerInterface $controller, string $method): void
    {
        $psr17 = new Psr17Factory();
        $kernel = new WpHttpKernel(
            $this->createStub(ContainerInterface::class),
            new RouteCollection(),
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );

        (new ReflectionMethod($kernel, 'applyPlatformAuth'))->invoke($kernel, $controller, $method);
    }
}

class FixtureNonceController implements ControllerInterface
{
    #[Nonce(action: 'do-thing')]
    public function guarded(): void {}

    #[Nonce(action: 'custom-action', param: 'custom_nonce')]
    public function customParam(): void {}

    #[Nonce(action: 'do-thing', require: false)]
    public function optional(): void {}

    public function open(): void {}

    public function handle(): void {}

    public function setContainer(ContainerInterface $container): void {}

    public function setRequest(\Symfony\Component\HttpFoundation\Request $request): void {}

    public function preHandle(): void {}

    public function setRequireLogin(): void {}

    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void {}
}

#[Nonce(action: 'class-wide')]
final class FixtureClassNonceController extends FixtureNonceController
{
    public function anything(): void {}
}
