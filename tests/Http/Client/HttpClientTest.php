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

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\WordPress\Http\Client\HttpClient;
use Middag\WordPress\Http\Client\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
        unset($GLOBALS['__wp_test_http_response']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_http_requests'],
            $GLOBALS['__wp_test_http_response'],
            $GLOBALS['__wp_test_actions'],
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
    public function certificateArgsNeverReachWpHttpAndArmTheCurlAction(): void
    {
        (new HttpClient())->get('https://bank.example.test', [
            'certPath' => '/secrets/client.pem',
            'certPassword' => 'secret',
            'keyPath' => '/secrets/client.key',
        ]);

        $request = $GLOBALS['__wp_test_http_requests'][0];
        self::assertArrayNotHasKey('certPath', $request['args']);
        self::assertArrayNotHasKey('certPassword', $request['args']);
        self::assertArrayNotHasKey('keyPath', $request['args']);

        self::assertArrayHasKey('http_api_curl', $GLOBALS['__wp_test_actions'], 'mTLS goes through the http_api_curl action');
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
