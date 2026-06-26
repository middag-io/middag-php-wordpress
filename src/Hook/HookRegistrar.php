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

use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class HookRegistrar
{
    public function __construct(
        private ?ContainerInterface $container = null,
        private string $hookNamespace = 'Middag\\',
        private ?string $hookDir = null,
    ) {}

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
        $hookDir = $this->hookDir ?? dirname(__DIR__, 2) . '/WordPress';
        $classes = [];

        if (!is_dir($hookDir)) {
            return $classes;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($hookDir, RecursiveDirectoryIterator::SKIP_DOTS),
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
                [$hookDir . '/', dirname(__DIR__, 2) . '/', '/', '.php'],
                ['', '', '\\', ''],
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
