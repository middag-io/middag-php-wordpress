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

use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WordPress\Http\WpRestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Fail-closed edges of {@see WpRestKernel::authorize()}: the logged-out user,
 * a throw while the requirements are resolved, invalid object ids and the
 * unread reference host.
 *
 * @internal
 */
#[CoversClass(WpRestKernel::class)]
final class WpRestKernelAuthorizeFailClosedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_test_user_caps'] = [];
        $GLOBALS['__wp_test_user_can_calls'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_user_caps'], $GLOBALS['__wp_test_user_can_calls']);
        parent::tearDown();
    }

    #[Test]
    public function authorizeRejectsTheLoggedOutUserOnALoginOnlyRoute(): void
    {
        $result = $this->kernelFor(new WP_User(0))
            ->authorize(FailClosedTestController::class, 'loginOnlyAction', $this->request());

        $this->assertDenied($result, 'unauthorized', 401);
    }

    #[Test]
    public function authorizeRejectsTheLoggedOutUserBeforeAnyCapabilityCheck(): void
    {
        $GLOBALS['__wp_test_user_caps'][0]['read'] = true;

        $result = $this->kernelFor(new WP_User(0))
            ->authorize(FailClosedTestController::class, 'readAction', $this->request());

        $this->assertDenied($result, 'unauthorized', 401);
        self::assertSame([], $GLOBALS['__wp_test_user_can_calls']);
    }

    #[Test]
    public function authorizeDeniesAndLogsWhenADefinitionClassThrowsWhileResolvingItsCapability(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            self::isString(),
            self::callback(static fn (array $context): bool => $context['exception'] instanceof RuntimeException
                && $context['action'] === 'throwingDefinitionClassAction'),
        );

        $result = $this->kernelFor(new WP_User(7), $this->containerWithLogger($logger))
            ->authorize(FailClosedTestController::class, 'throwingDefinitionClassAction', $this->request());

        $this->assertDenied($result, 'forbidden', 403);
        self::assertSame([], $GLOBALS['__wp_test_user_can_calls']);
    }

    #[Test]
    public function authorizeDeniesAndLogsWhenTheAttributeThrowsWhileItIsBuilt(): void
    {
        // A definition object is asked for its reference when the attribute is
        // instantiated, so its throw surfaces while the attribute is resolved.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $result = $this->kernelFor(new WP_User(7), $this->containerWithLogger($logger))
            ->authorize(FailClosedTestController::class, 'throwingDefinitionObjectAction', $this->request());

        $this->assertDenied($result, 'forbidden', 403);
    }

    #[Test]
    public function authorizeStillDeniesWhenTheLoggerThrows(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willThrowException(new RuntimeException('log sink down'));

        $result = $this->kernelFor(new WP_User(7), $this->containerWithLogger($logger))
            ->authorize(FailClosedTestController::class, 'throwingDefinitionClassAction', $this->request());

        $this->assertDenied($result, 'forbidden', 403);
    }

    #[Test]
    #[DataProvider('invalidObjectIdActions')]
    public function authorizeDeniesAnExplicitButInvalidObjectId(string $action): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['edit_post'] = true;

        $result = $this->kernelFor(new WP_User(7))
            ->authorize(FailClosedTestController::class, $action, $this->request());

        $this->assertDenied($result, 'forbidden', 403);
        self::assertSame([], $GLOBALS['__wp_test_user_can_calls']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidObjectIdActions(): iterable
    {
        yield 'option zero' => ['zeroOptionAction'];

        yield 'option negative' => ['negativeOptionAction'];

        yield 'option not numeric' => ['wordOptionAction'];

        yield 'option exponent string' => ['exponentOptionAction'];

        yield 'option float' => ['floatOptionAction'];

        yield 'attribute negative' => ['negativeAttributeAction'];
    }

    #[Test]
    public function authorizePassesAPositiveNumericStringOptionAsAnInteger(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['edit_post'] = true;

        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(FailClosedTestController::class, 'stringOptionAction', $this->request()));
        self::assertSame([7], $GLOBALS['__wp_test_user_can_calls'][0]['args']);
    }

    #[Test]
    public function authorizeChecksAPrimitiveCapabilityWithoutAnObjectIdWhenNoneIsGiven(): void
    {
        $GLOBALS['__wp_test_user_caps'][7]['read'] = true;

        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(FailClosedTestController::class, 'readAction', $this->request()));
        self::assertSame([], $GLOBALS['__wp_test_user_can_calls'][0]['args']);
    }

    #[Test]
    public function authorizeChecksTheReferenceKeyWhateverItsHost(): void
    {
        // The framework defines no host convention, so the adapter does not
        // read the host: the key alone goes to user_can().
        $GLOBALS['__wp_test_user_caps'][7]['manage_reports'] = true;

        self::assertTrue($this->kernelFor(new WP_User(7))
            ->authorize(FailClosedTestController::class, 'foreignHostAction', $this->request()));
        self::assertSame('manage_reports', $GLOBALS['__wp_test_user_can_calls'][0]['capability']);

        $GLOBALS['__wp_test_user_caps'] = [];

        $this->assertDenied(
            $this->kernelFor(new WP_User(7))->authorize(FailClosedTestController::class, 'foreignHostAction', $this->request()),
            'forbidden',
            403,
        );
    }

    private function assertDenied(mixed $result, string $code, int $status): void
    {
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame($code, $result->get_error_code());
        self::assertSame($status, $result->get_error_data()['status']);
    }

    private function kernelFor(WP_User $user, ?ContainerInterface $container = null): WpRestKernel
    {
        $authenticator = $this->createStub(RequestAuthenticatorInterface::class);
        $authenticator->method('resolveUser')->willReturn($user);

        return new WpRestKernel($container ?? $this->createStub(ContainerInterface::class), $authenticator);
    }

    private function containerWithLogger(LoggerInterface $logger): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === LoggerInterface::class);
        $container->method('get')->willReturn($logger);

        return $container;
    }

    private function request(): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_method('GET');

        return $request;
    }
}

/**
 * Controller double for the fail-closed authorization edges.
 *
 * @internal
 */
final class FailClosedTestController
{
    public function loginOnlyAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(capabilities: ['read'])]
    public function readAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [FailClosedThrowingDefinition::class])]
    public function throwingDefinitionClassAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [new FailClosedThrowingDefinition()])]
    public function throwingDefinitionObjectAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(instanceId: 42, requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => 0])])]
    public function zeroOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => -3])])]
    public function negativeOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(instanceId: 42, requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => 'abc'])])]
    public function wordOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => '1e3'])])]
    public function exponentOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => 1.5])])]
    public function floatOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(capabilities: ['edit_post'], instanceId: -5)]
    public function negativeAttributeAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(requirements: [new CapabilityRequirement(new CapabilityReference('edit_post'), options: ['instanceid' => '7'])])]
    public function stringOptionAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }

    #[Auth(capabilities: [new CapabilityReference('manage_reports', 'another-host')])]
    public function foreignHostAction(): WP_REST_Response
    {
        return RestResponse::success([]);
    }
}

/**
 * Capability definition whose reference cannot be resolved.
 *
 * @internal
 */
final class FailClosedThrowingDefinition implements CapabilityDefinitionInterface
{
    public function capabilityReference(): CapabilityReference
    {
        throw new RuntimeException('capability store unavailable');
    }

    public function capabilityOptions(): array
    {
        return [];
    }
}
