<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\WordPress\Filesystem\WpUploadsFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpUploadsFilesystem::class)]
final class WpUploadsFilesystemTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/middag-uploads-' . uniqid();
        $GLOBALS['__wp_test_upload_dir'] = [
            'basedir' => $this->baseDir,
            'baseurl' => 'http://example.test/wp-content/uploads',
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_test_upload_dir']);
        if (is_dir($this->baseDir)) {
            exec('rm -rf ' . escapeshellarg($this->baseDir));
        }
    }

    #[Test]
    public function writeReadExistsDeleteRoundTripInsideTheUploadsJail(): void
    {
        $filesystem = new WpUploadsFilesystem('acme');

        $filesystem->write('reports/summary.txt', 'hello');

        self::assertTrue($filesystem->exists('reports/summary.txt'));
        self::assertSame('hello', $filesystem->read('reports/summary.txt'));
        self::assertFileExists($this->baseDir . '/acme/reports/summary.txt', 'rooted under uploads/{subdirectory}');

        $filesystem->delete('reports/summary.txt');
        self::assertFalse($filesystem->exists('reports/summary.txt'));
    }

    #[Test]
    public function unresolvableUploadsDirThrows(): void
    {
        $GLOBALS['__wp_test_upload_dir'] = ['basedir' => ''];

        $this->expectException(MiddagInfrastructureException::class);

        new WpUploadsFilesystem();
    }
}
