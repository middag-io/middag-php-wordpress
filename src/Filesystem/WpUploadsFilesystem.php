<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Filesystem\LocalFilesystem;
use Middag\WordPress\Support\UploadSupport;

/**
 * WordPress implementation of the framework filesystem port, rooted at the
 * site's uploads directory (`wp_upload_dir()['basedir']`) — the one location
 * a plugin may write to on every WP host. Delegates to the framework's
 * {@see LocalFilesystem} for the actual path-jailed IO.
 *
 * @api
 */
final readonly class WpUploadsFilesystem implements FilesystemInterface
{
    private FilesystemInterface $inner;

    /**
     * @param string $subdirectory optional namespace under uploads (e.g. "acme")
     *
     * @throws MiddagInfrastructureException when the uploads directory cannot be resolved
     */
    public function __construct(string $subdirectory = '', ?string $baseDir = null)
    {
        $root = $baseDir ?? UploadSupport::baseDir();

        if ($root === '') {
            throw new MiddagInfrastructureException('Cannot resolve the WordPress uploads directory outside a WP runtime.');
        }

        if ($subdirectory !== '') {
            $root .= '/' . trim($subdirectory, '/');
        }

        if (!is_dir($root) && !mkdir($root, 0o755, true) && !is_dir($root)) {
            throw new MiddagInfrastructureException(sprintf('Cannot create uploads directory "%s".', $root));
        }

        $this->inner = new LocalFilesystem($root);
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($path);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function write(string $path, string $contents): void
    {
        $this->inner->write($path, $contents);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }
}
