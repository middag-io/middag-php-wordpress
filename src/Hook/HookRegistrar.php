<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Hook;

use Middag\WordPress\Exception\HookRegistrationException;
use Middag\WordPress\Hook\Contract\HookInterface;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers `*Hooks` classes under an explicit directory and registers every
 * {@see HookInterface} implementation found (container-resolved when the
 * consumer provides one). The FQCN of each hook is derived from the path
 * relative to `$hookDir`, prefixed with `$hookNamespace`.
 *
 * @api
 */
final readonly class HookRegistrar
{
    private string $hookDir;

    public function __construct(
        private ?ContainerInterface $container = null,
        private string $hookNamespace = 'Middag\\',
        ?string $hookDir = null,
    ) {
        if ($hookDir === null || !is_dir($hookDir)) {
            throw new HookRegistrationException(sprintf(
                'HookRegistrar requires an explicit, existing hook directory; got %s.',
                $hookDir === null ? 'null' : sprintf('"%s"', $hookDir),
            ));
        }

        $this->hookDir = $hookDir;
    }

    public function register(): void
    {
        $hookClasses = $this->discoverHooks();

        foreach ($hookClasses as $className) {
            if ($this->container instanceof ContainerInterface && $this->container->has($className)) {
                $hook = $this->container->get($className);
            } else {
                $hook = new $className();
            }

            if ($hook instanceof HookInterface) {
                $hook->register();
            }
        }
    }

    private function discoverHooks(): array
    {
        $classes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->hookDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getBasename('.php');

            if (!str_ends_with($filename, 'Hooks')) {
                continue;
            }

            $relativePath = str_replace(
                [$this->hookDir . '/', '/', '.php'],
                ['', '\\', ''],
                $file->getPathname()
            );

            $className = $this->hookNamespace . $relativePath;

            if (class_exists($className) && is_subclass_of($className, HookInterface::class)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }
}
