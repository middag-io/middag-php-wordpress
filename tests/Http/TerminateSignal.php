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

use RuntimeException;

/**
 * Thrown by {@see RecordingEmitter::terminate()} to stand in for the production
 * `exit`, so tests can assert a pipeline reached its termination point (and
 * inspect the recorded status/headers/body) without ending the PHP process.
 *
 * @internal
 */
final class TerminateSignal extends RuntimeException {}
