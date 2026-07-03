<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Client;

use Closure;
use CurlHandle;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\WordPress\Support\HookSupport;

/**
 * Outbound HTTP client over the WordPress HTTP API (`wp_remote_request()`),
 * with first-class client-certificate (mTLS) support — WP_Http exposes no
 * certificate arguments, so `certPath`/`certPassword`/`keyPath` are applied
 * through an `http_api_curl` action registered before and detached right
 * after the single request. When the active transport never hands the action
 * a cURL handle (non-cURL transport), the request fails loudly instead of
 * silently going out without the client certificate.
 *
 * Custom args (everything else passes straight to `wp_remote_request()`):
 *  - `certPath` / `certPassword` / `keyPath` — client certificate (mTLS)
 *
 * @api
 */
final readonly class HttpClient
{
    /**
     * @param array<string, mixed> $defaultArgs merged under every request's args
     */
    public function __construct(
        private array $defaultArgs = [],
    ) {}

    /**
     * @param array<string, mixed> $args
     *
     * @throws MiddagInfrastructureException on transport failure or outside a WP runtime
     */
    public function get(string $url, array $args = []): HttpResponse
    {
        return $this->request('GET', $url, $args);
    }

    /**
     * @param array<mixed>|string  $body
     * @param array<string, mixed> $args
     *
     * @throws MiddagInfrastructureException on transport failure or outside a WP runtime
     */
    public function post(string $url, array|string $body = [], array $args = []): HttpResponse
    {
        return $this->request('POST', $url, ['body' => $body] + $args);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @throws MiddagInfrastructureException on transport failure or outside a WP runtime
     */
    public function request(string $method, string $url, array $args = []): HttpResponse
    {
        if (!\function_exists('wp_remote_request')) {
            throw new MiddagInfrastructureException('WordPress HTTP API is unavailable outside a WP runtime.');
        }

        $args = array_replace($this->defaultArgs, $args, ['method' => strtoupper($method)]);
        $certificate = $this->extractCertificate($args);

        $armed = false;
        $curlAction = null;

        if ($certificate !== []) {
            $curlAction = $this->armCertificate($certificate, $armed);
        }

        $result = wp_remote_request($url, $args);

        if ($curlAction instanceof Closure) {
            HookSupport::removeAction('http_api_curl', $curlAction);

            if (!$armed) {
                throw new MiddagInfrastructureException(sprintf(
                    'HTTP %s %s: the active WP_Http transport did not apply the client certificate '
                    . '(http_api_curl never received a cURL handle) — refusing to proceed without mTLS.',
                    $args['method'],
                    $url,
                ));
            }
        }

        if (is_wp_error($result)) {
            throw new MiddagInfrastructureException(sprintf(
                'HTTP %s %s failed: %s',
                $args['method'],
                $url,
                $result->get_error_message(),
            ));
        }

        $headers = wp_remote_retrieve_headers($result);

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($result),
            \is_array($headers) ? $headers : iterator_to_array($headers),
            wp_remote_retrieve_body($result),
        );
    }

    /**
     * Pull the mTLS args out of the request args (they are ours, not WP_Http's).
     *
     * @param array<string, mixed> $args
     *
     * @return array{certPath?: string, certPassword?: string, keyPath?: string}
     */
    private function extractCertificate(array &$args): array
    {
        $certificate = [];

        foreach (['certPath', 'certPassword', 'keyPath'] as $key) {
            if (isset($args[$key]) && \is_string($args[$key]) && $args[$key] !== '') {
                $certificate[$key] = $args[$key];
            }
            unset($args[$key]);
        }

        return $certificate;
    }

    /**
     * Register the `http_api_curl` action that injects the client certificate
     * into the request's cURL handle, and return the registered closure so the
     * caller can detach it right after the request (no cross-request
     * accumulation). `$armed` flips to true only when the certificate was
     * actually applied to a cURL handle, letting the caller detect transports
     * that silently skip the action (non-cURL) and refuse to degrade.
     *
     * @param array{certPath?: string, certPassword?: string, keyPath?: string} $certificate
     *
     * @param-out bool $armed
     */
    private function armCertificate(array $certificate, bool &$armed): Closure
    {
        $armed = false;

        $action = static function (mixed $handle) use (&$armed, $certificate): void {
            if (!$handle instanceof CurlHandle) {
                return;
            }

            if (isset($certificate['certPath'])) {
                curl_setopt($handle, CURLOPT_SSLCERT, $certificate['certPath']);
            }
            if (isset($certificate['certPassword'])) {
                curl_setopt($handle, CURLOPT_SSLCERTPASSWD, $certificate['certPassword']);
            }
            if (isset($certificate['keyPath'])) {
                curl_setopt($handle, CURLOPT_SSLKEY, $certificate['keyPath']);
            }

            $armed = true;
        };

        HookSupport::addAction('http_api_curl', $action);

        return $action;
    }
}
