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

use Middag\Framework\Exception\MiddagInfrastructureException;

/**
 * Normalized outbound HTTP response returned by {@see HttpClient}.
 *
 * @api
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Decode the body as JSON.
     *
     * @return array<mixed>
     *
     * @throws MiddagInfrastructureException when the body is not valid JSON
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        if (!\is_array($decoded)) {
            throw new MiddagInfrastructureException('HTTP response body is not valid JSON.');
        }

        return $decoded;
    }
}
