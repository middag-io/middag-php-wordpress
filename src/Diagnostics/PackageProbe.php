<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics;

use InvalidArgumentException;
use Middag\WordPress\Diagnostics\Contract\DiagnosticCheckInterface;
use Middag\WordPress\Runtime\Kernel;

/**
 * A package's declaration to the boot report.
 *
 * Holds only names, never instances, so declaring a probe loads nothing and
 * boots nothing: the report resolves the classes when it is collected. The
 * built-in checks it drives are:
 *
 * - `autoload`: the entry class (or the kernel class when no entry class is
 *   given) resolves through the autoloader, and from which file, which shows
 *   whether the plugin's own `vendor/` or a shared one won;
 * - `kernel`: the kernel subclass reports {@see Kernel::isBooted()} (read-only:
 *   it never boots the kernel);
 * - `version`: the package declared a version.
 *
 * Any further {@see DiagnosticCheckInterface} runs after them, in order.
 *
 * Example (main plugin file, right after the autoloader, before the kernel
 * boots, so a failed boot is still reported):
 *
 *     (new DiagnosticsRegistry('acme_diagnostics'))->register(new PackageProbe(
 *         name: 'acme-shop',
 *         version: ACME_SHOP_VERSION,
 *         kernelClass: AcmeShopKernel::class,
 *     ));
 *
 * @api
 */
final readonly class PackageProbe
{
    /**
     * @param string                         $name        stable package identifier, matched by the
     *                                                    command's expected-package list
     * @param string                         $version     declared package version; empty is a warning
     * @param null|class-string<Kernel>      $kernelClass the package's kernel subclass, when it has one
     * @param null|class-string              $entryClass  class proving the autoloader resolved the
     *                                                    package; defaults to the kernel class
     * @param list<DiagnosticCheckInterface> $checks      further package or host checks
     *
     * @throws InvalidArgumentException when the name is empty
     */
    public function __construct(
        public string $name,
        public string $version = '',
        public ?string $kernelClass = null,
        public ?string $entryClass = null,
        public array $checks = [],
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A diagnostics package probe needs a non-empty name.');
        }
    }

    /**
     * The class the autoload check resolves, or null when none was declared.
     */
    public function autoloadClass(): ?string
    {
        return $this->entryClass ?? $this->kernelClass;
    }
}
