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

use Middag\WordPress\Http\Contract\ResponseEmitterInterface;

/**
 * In-memory {@see ResponseEmitterInterface} test double: records the status,
 * headers, redirect target and body, and throws {@see TerminateSignal} instead
 * of exiting, so the adapter's exit/echo/header paths become assertable.
 */
final class RecordingEmitter implements ResponseEmitterInterface
{
    public ?int $status = null;

    /** @var array<string, string> */
    public array $headers = [];

    public ?string $redirectedTo = null;

    public string $body = '';

    public function status(int $code): void
    {
        $this->status = $code;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function redirect(string $url): void
    {
        $this->redirectedTo = $url;
    }

    public function write(string $body): void
    {
        $this->body .= $body;
    }

    public function terminate(): never
    {
        throw new TerminateSignal();
    }
}
