<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Inertia;

use Closure;
use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Http\Security\CsrfGuard;
use Middag\WordPress\Support\AssetSupport;
use Middag\WordPress\Support\EscapeSupport;
use Middag\WordPress\Support\SecuritySupport;

final class InertiaAdapter
{
    private static array $sharedProps = [];

    public static function share(string $key, mixed $value): void
    {
        self::$sharedProps[$key] = $value;
    }

    /**
     * Clear all shared props. Test-isolation seam so suites can reset the static
     * accumulator without reflecting into the private property.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$sharedProps = [];
    }

    public static function render(string $component, array $props = []): void
    {
        $page = [
            'component' => $component,
            'props' => self::resolveProps($props),
            'url' => self::getCurrentUrl(),
            'version' => self::getVersion(),
        ];

        if (self::isInertiaRequest()) {
            self::sendJson($page);
        }

        self::renderHtml($page);
    }

    public static function location(string $url): never
    {
        if (self::isInertiaRequest()) {
            header('X-Inertia-Location: ' . $url);
            http_response_code(409);

            exit;
        }

        wp_redirect($url);

        exit;
    }

    public static function isInertiaRequest(): bool
    {
        return isset($_SERVER['HTTP_X_INERTIA']) && $_SERVER['HTTP_X_INERTIA'] === 'true';
    }

    /**
     * Register + enqueue the SPA bundle (and optional stylesheet) for the host
     * component, cache-busted by the host's asset version from {@see HostContext}.
     *
     * Host-neutral by design: the host plugin supplies the asset URLs — only it
     * knows its own {@see plugins_url()} — while the adapter contributes the
     * WordPress enqueue wiring and the version. This is deliberately independent
     * of {@see render()} (which emits only the mount node); call it from the
     * host's `admin_enqueue_scripts` / `wp_enqueue_scripts` hook.
     */
    public static function enqueueAssets(
        string $handle,
        string $scriptSrc,
        ?string $styleSrc = null,
        array $scriptDeps = [],
    ): void {
        $version = self::getVersion();

        AssetSupport::enqueueScript($handle, $scriptSrc, $scriptDeps, $version, true);

        if ($styleSrc !== null) {
            AssetSupport::enqueueStyle($handle, $styleSrc, [], $version);
        }
    }

    private static function resolveProps(array $props): array
    {
        $merged = array_merge(self::$sharedProps, $props);

        // Auto-share the WP nonce as `csrfToken` so the SPA can echo it back via
        // the `X-WP-Nonce` header that CsrfGuard verifies on mutating requests.
        // An explicit share/prop of the same key wins (set before this point).
        if (!array_key_exists('csrfToken', $merged)) {
            $merged['csrfToken'] = SecuritySupport::createNonce(CsrfGuard::NONCE_ACTION);
        }

        $resolved = [];

        foreach ($merged as $key => $value) {
            $resolved[$key] = $value instanceof Closure ? $value() : $value;
        }

        // Auto-normalize PageContract to @middag-io/react v0.15+ schema
        if (isset($resolved['contract']) && is_array($resolved['contract'])) {
            $resolved['contract'] = PageContractNormalizer::normalize($resolved['contract']);
        }

        return $resolved;
    }

    private static function isPartialReload(string $component): bool
    {
        return isset($_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'])
            && $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'] === $component;
    }

    private static function getPartialData(): array
    {
        $header = $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'] ?? '';

        return $header !== '' ? explode(',', $header) : [];
    }

    private static function getCurrentUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/wp-admin/admin.php?page=middag';
    }

    private static function getVersion(): string
    {
        return HostContext::get()?->assetVersion() ?? '5.0.0';
    }

    private static function sendJson(array $page): never
    {
        // Handle partial reloads
        if (self::isPartialReload($page['component'])) {
            $only = self::getPartialData();
            if ($only !== []) {
                $page['props'] = array_intersect_key($page['props'], array_flip($only));
            }
        }

        header('Content-Type: application/json');
        header('X-Inertia: true');
        header('Vary: X-Inertia');

        echo wp_json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        exit;
    }

    private static function renderHtml(array $page): void
    {
        $json = wp_json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        // Escape exactly once at the output boundary, through the Escape seam.
        // The JSON above is the framework's internal page payload — it is NOT
        // pre-escaped, so this attr() call is the single (and only) HTML-attribute
        // escaping layer over the data-page value. Do not add another.
        $attr = EscapeSupport::attr($json);

        echo '<div id="middag-app" data-page="' . $attr . '"></div>';
    }
}
