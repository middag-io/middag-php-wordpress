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
use Middag\WordPress\Http\PhpSapiEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of {@see PhpSapiEmitter}, the production
 * {@see ResponseEmitterInterface}. Other tests
 * (InertiaAdapter, CsrfGuard, AdminRouteRegistrar, ...) exercise the pipeline
 * classes that consume this seam through the {@see RecordingEmitter} test
 * double instead, so this concrete implementation itself had no direct
 * coverage. `terminate()` calls the real `exit`, which would kill the whole
 * PHPUnit process if invoked in-process — it is instead verified by running it
 * in a child PHP process and asserting the process exits cleanly without
 * reaching code after the call.
 *
 * @internal
 */
#[CoversClass(PhpSapiEmitter::class)]
final class PhpSapiEmitterTest extends TestCase
{
    private int $originalResponseCode;

    protected function setUp(): void
    {
        $this->originalResponseCode = http_response_code() ?: 200;
    }

    protected function tearDown(): void
    {
        // Restore the SAPI response code — it is real, process-wide state and
        // must not leak into unrelated tests running later in this process.
        http_response_code($this->originalResponseCode);
    }

    #[Test]
    public function statusSetsTheHttpResponseCode(): void
    {
        $emitter = new PhpSapiEmitter();

        $emitter->status(201);

        self::assertSame(201, http_response_code());
    }

    #[Test]
    public function statusCanBeChangedAcrossCalls(): void
    {
        $emitter = new PhpSapiEmitter();

        $emitter->status(404);
        self::assertSame(404, http_response_code());

        $emitter->status(500);
        self::assertSame(500, http_response_code());
    }

    #[Test]
    public function headerSendsTheGivenNameValuePairWithoutThrowing(): void
    {
        $emitter = new PhpSapiEmitter();

        // header() has no return value and no PHP-level introspection API in
        // the general case; under the CLI SAPI used for this test suite
        // headers_sent() never becomes true, so the call always reaches the
        // real header() function here. The primary assertion is that it
        // completes without error. When ext-xdebug is loaded (as in this
        // suite's environment), xdebug_get_headers() additionally lets us
        // assert the exact header that was sent.
        $emitter->header('X-Middag-Test', 'hello');

        if (function_exists('xdebug_get_headers')) {
            self::assertContains('X-Middag-Test: hello', xdebug_get_headers());
        } else {
            self::assertTrue(true, 'header() completed without throwing');
        }
    }

    #[Test]
    public function redirectDelegatesToWpRedirect(): void
    {
        $GLOBALS['__wp_test_redirects'] = [];
        $emitter = new PhpSapiEmitter();

        $emitter->redirect('https://example.test/next');

        self::assertSame(
            [['location' => 'https://example.test/next', 'status' => 302]],
            $GLOBALS['__wp_test_redirects'],
        );

        unset($GLOBALS['__wp_test_redirects']);
    }

    #[Test]
    public function writeEchoesTheBodyVerbatim(): void
    {
        $emitter = new PhpSapiEmitter();

        ob_start();
        $emitter->write('<p>hello</p>');
        $output = ob_get_clean();

        self::assertSame('<p>hello</p>', $output);
    }

    #[Test]
    public function writeCanBeCalledMultipleTimesAndAccumulatesInOrder(): void
    {
        $emitter = new PhpSapiEmitter();

        ob_start();
        $emitter->write('first-');
        $emitter->write('second');
        $output = ob_get_clean();

        self::assertSame('first-second', $output);
    }

    #[Test]
    public function terminateExitsTheProcessWithoutReachingSubsequentCode(): void
    {
        $autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = <<<PHP
            <?php
            require '{$autoloader}';
            (new \\Middag\\WordPress\\Http\\PhpSapiEmitter())->terminate();
            echo 'UNREACHABLE';
            PHP;

        $scriptPath = tempnam(sys_get_temp_dir(), 'middag_phpsapiemitter_terminate_');
        self::assertIsString($scriptPath);
        file_put_contents($scriptPath, $script);

        try {
            $output = [];
            $exitCode = null;
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $exitCode);

            self::assertSame(0, $exitCode, 'terminate() must exit the process cleanly');
            self::assertStringNotContainsString('UNREACHABLE', implode("\n", $output));
        } finally {
            unlink($scriptPath);
        }
    }
}
