<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Client;

use CurlHandle;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\WordPress\Http\Client\HttpClient;
use Middag\WordPress\Http\Client\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use WP_Error;

/**
 * @internal
 */
#[CoversClass(HttpClient::class)]
#[CoversClass(HttpResponse::class)]
final class HttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_http_requests'] = [];
        $GLOBALS['__wp_test_actions'] = [];
        unset($GLOBALS['__wp_test_http_response'], $GLOBALS['__wp_test_http_curl_handle']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_http_requests'],
            $GLOBALS['__wp_test_http_response'],
            $GLOBALS['__wp_test_actions'],
            $GLOBALS['__wp_test_http_curl_handle'],
        );
    }

    #[Test]
    public function getMergesDefaultArgsAndNormalizesTheResponse(): void
    {
        $GLOBALS['__wp_test_http_response'] = [
            'response' => ['code' => 201, 'message' => 'Created'],
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"id":7}',
        ];

        $client = new HttpClient(['timeout' => 15]);
        $response = $client->get('https://api.example.test/items', ['headers' => ['X-A' => '1']]);

        $request = $GLOBALS['__wp_test_http_requests'][0];
        self::assertSame('https://api.example.test/items', $request['url']);
        self::assertSame('GET', $request['args']['method']);
        self::assertSame(15, $request['args']['timeout'], 'default args merge under request args');
        self::assertSame(['X-A' => '1'], $request['args']['headers']);

        self::assertSame(201, $response->status);
        self::assertTrue($response->ok());
        self::assertSame(['id' => 7], $response->json());
    }

    #[Test]
    public function postSendsBodyAndUppercasesMethod(): void
    {
        (new HttpClient())->post('https://api.example.test/items', ['name' => 'x']);

        $request = $GLOBALS['__wp_test_http_requests'][0];
        self::assertSame('POST', $request['args']['method']);
        self::assertSame(['name' => 'x'], $request['args']['body']);
    }

    #[Test]
    public function certificateIsAppliedThroughTheCurlActionAndTheActionIsRemoved(): void
    {
        $handle = curl_init();
        self::assertInstanceOf(CurlHandle::class, $handle);
        $GLOBALS['__wp_test_http_curl_handle'] = $handle;

        $response = (new HttpClient())->get('https://bank.example.test', [
            'certPath' => '/secrets/client.pem',
            'certPassword' => 'secret',
            'keyPath' => '/secrets/client.key',
        ]);

        self::assertSame(200, $response->status, 'cURL transport applied the cert: no exception');

        $request = $GLOBALS['__wp_test_http_requests'][0];
        self::assertArrayNotHasKey('certPath', $request['args'], 'mTLS args never reach WP_Http');
        self::assertArrayNotHasKey('certPassword', $request['args']);
        self::assertArrayNotHasKey('keyPath', $request['args']);

        self::assertSame(
            [],
            $GLOBALS['__wp_test_actions']['http_api_curl'] ?? [],
            'the one-shot closure is detached right after the request (no accumulation)',
        );
    }

    #[Test]
    public function certificateOverNonCurlTransportThrowsInsteadOfSilentlyDegrading(): void
    {
        // No $__wp_test_http_curl_handle: the http_api_curl action never fires,
        // as with a non-cURL WP_Http transport (streams).
        try {
            (new HttpClient())->get('https://bank.example.test/pay', ['certPath' => '/secrets/client.pem']);
            self::fail('expected MiddagInfrastructureException when the transport never applies the cert');
        } catch (MiddagInfrastructureException $middagInfrastructureException) {
            self::assertStringContainsString('GET', $middagInfrastructureException->getMessage());
            self::assertStringContainsString('https://bank.example.test/pay', $middagInfrastructureException->getMessage());
            self::assertStringContainsString('certificate', $middagInfrastructureException->getMessage());
        }

        self::assertSame(
            [],
            $GLOBALS['__wp_test_actions']['http_api_curl'] ?? [],
            'the closure is detached even when the request fails the mTLS guard',
        );
    }

    #[Test]
    public function aNonCurlHandleHandedToTheActionStillThrowsWithoutArmingTheCertificate(): void
    {
        // Unlike the "action never fires" case above, this transport DOES fire
        // http_api_curl — but hands it something other than a CurlHandle (e.g. a
        // transport bug, or a future WP_Http backend). The guard inside the
        // closure must reject it just as loudly as a transport that never fires.
        $GLOBALS['__wp_test_http_curl_handle'] = new stdClass();

        try {
            (new HttpClient())->get('https://bank.example.test/pay', ['certPath' => '/secrets/client.pem']);
            self::fail('expected MiddagInfrastructureException when the handle is not a CurlHandle');
        } catch (MiddagInfrastructureException $middagInfrastructureException) {
            self::assertStringContainsString('never received a cURL handle', $middagInfrastructureException->getMessage());
        }
    }

    #[Test]
    public function requestWithoutCertificateArmsNothing(): void
    {
        $handle = curl_init();
        self::assertInstanceOf(CurlHandle::class, $handle);
        $GLOBALS['__wp_test_http_curl_handle'] = $handle;

        $response = (new HttpClient())->get('https://api.example.test');

        self::assertSame(200, $response->status);
        self::assertArrayNotHasKey(
            'http_api_curl',
            $GLOBALS['__wp_test_actions'],
            'no certificate args: no http_api_curl action is ever registered',
        );
    }

    #[Test]
    public function transportErrorThrows(): void
    {
        $GLOBALS['__wp_test_http_response'] = new WP_Error('http_request_failed', 'cURL error 28');

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('cURL error 28');

        (new HttpClient())->get('https://api.example.test');
    }

    #[Test]
    public function invalidJsonBodyThrowsOnDecode(): void
    {
        $GLOBALS['__wp_test_http_response'] = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => 'not-json',
        ];

        $response = (new HttpClient())->get('https://api.example.test');

        $this->expectException(MiddagInfrastructureException::class);
        $response->json();
    }
}
