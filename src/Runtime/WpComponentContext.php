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
use Middag\Framework\Kernel\HostContext;

/**
 * Neutral WordPress host context.
 *
 * The host plugin's composition root builds this once at boot and registers it
 * via {@see HostContext::set()} so adapter helpers
 * resolve the host's identity, asset version, and base path without referencing
 * any specific consumer plugin or its global constants.
 *
 * Example (host plugin bootstrap):
 *
 *     HostContext::set(new WpComponentContext(
 *         componentName: 'my-plugin',
 *         assetVersion: MY_PLUGIN_VERSION,
 *         basePath: plugin_dir_path(__FILE__),
 *     ));
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
