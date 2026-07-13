<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Runtime;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;

/**
 * Neutral WordPress host context.
 *
 * The host plugin's composition root builds this once at boot and injects it
 * into the adapter's per-component services (InertiaAdapter, EmailSender, ...)
 * through its own DI container, so they resolve the host's identity, asset
 * version, and base path without referencing any specific consumer plugin or
 * its global constants — and without sharing a process-wide slot with other
 * plugins in the same request.
 *
 * Example (host plugin bootstrap):
 *
 *     $context = new WpComponentContext(
 *         componentName: 'my-plugin',
 *         assetVersion: MY_PLUGIN_VERSION,
 *         basePath: plugin_dir_path(__FILE__),
 *     );
 *     $inertia = new InertiaAdapter($context);
 *     $mailer = new EmailSender($logger, $context);
 *
 * @api
 */
final readonly class WpComponentContext implements HostComponentContextInterface
{
    public function __construct(
        private string $componentName,
        private string $assetVersion,
        private ?string $basePath = null,
    ) {}

    public function componentName(): string
    {
        return $this->componentName;
    }

    public function assetVersion(): string
    {
        return $this->assetVersion;
    }

    public function basePath(): ?string
    {
        return $this->basePath;
    }
}
