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
use ReflectionClass;

/**
 * Discovers every {@see HookInterface} implementation under the configured
 * roots and registers it (container-resolved when the consumer provides one).
 * The FQCN of each hook is derived from the path relative to its root,
 * prefixed with that root's namespace.
 *
 * Discovery follows the INTERFACE, never the filename. Matching on a `*Hooks`
 * suffix — or on a single root directory — makes registration depend on where
 * a file sits and what it is called, so a hook in the "wrong" folder, or named
 * in the singular, silently never registers: the class exists, it compiles,
 * its unit test passes, and `add_action` is never reached. That failure mode
 * cost real production behaviour, hence the contract here is the type.
 *
 * @api
 */
final readonly class HookRegistrar
{
    /** @var array<string, string> namespace prefix => absolute directory */
    private array $hookPaths;

    /**
     * @param array<string, string> $hookPaths namespace prefix => absolute directory
     */
    public function __construct(
        private ?ContainerInterface $container = null,
        array $hookPaths = [],
    ) {
        if ($hookPaths === []) {
            throw new HookRegistrationException(
                'HookRegistrar requires at least one namespace => directory pair.',
            );
        }

        foreach ($hookPaths as $namespace => $directory) {
            if (!is_dir($directory)) {
                throw new HookRegistrationException(sprintf(
                    'HookRegistrar received a non-existing directory for namespace "%s": "%s".',
                    $namespace,
                    $directory,
                ));
            }
        }

        $this->hookPaths = $hookPaths;
    }

    public function register(): void
    {
        $hookClasses = $this->discoverHooks();

        foreach ($hookClasses as $className) {
            if ($this->container instanceof ContainerInterface && $this->container->has($className)) {
                $hook = $this->container->get($className);
            } else {
                // Discovery by interface reaches hooks the container may not know.
                // Instantiating one that needs constructor arguments would raise a
                // bare ArgumentCountError from deep inside the boot, so name the
                // class and the fix instead.
                $constructor = (new ReflectionClass($className))->getConstructor();

                if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                    throw new HookRegistrationException(sprintf(
                        '%s requires constructor arguments but is not registered in the container. '
                        . 'Register it as a service, or give it a parameterless constructor.',
                        $className,
                    ));
                }

                $hook = new $className();
            }

            if ($hook instanceof HookInterface) {
                $hook->register();
            }
        }
    }

    /**
     * @return list<class-string<HookInterface>>
     */
    private function discoverHooks(): array
    {
        $classes = [];

        foreach ($this->hookPaths as $namespace => $directory) {
            foreach ($this->discoverIn($directory, $namespace) as $className) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * @return list<class-string<HookInterface>>
     */
    private function discoverIn(string $directory, string $namespace): array
    {
        $classes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(
                [$directory . '/', '/', '.php'],
                ['', '\\', ''],
                $file->getPathname()
            );

            $className = $namespace . $relativePath;

            if (class_exists($className) && is_subclass_of($className, HookInterface::class)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }
}
