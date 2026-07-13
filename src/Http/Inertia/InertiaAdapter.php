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
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\PhpSapiEmitter;
use Middag\WordPress\Http\Security\CsrfGuard;
use Middag\WordPress\Support\AssetSupport;
use Middag\WordPress\Support\EscapeSupport;
use Middag\WordPress\Support\SecuritySupport;

/**
 * Per-component Inertia adapter for the WordPress admin SPA pipeline.
 *
 * Instance-scoped: each host plugin builds one adapter from its own
 * {@see HostComponentContextInterface} (resolved through the plugin's own DI
 * container), so two plugins living in the same request never share shared-props,
 * mount id, CSRF nonce action, or asset version.
 *
 * Request/response boundaries follow the CsrfGuard split: the request superglobal
 * is read exactly once at the public entry points ({@see render()} / {@see
 * location()}) and threaded to pure helpers as an array, while every response
 * side-effect (headers, body, redirect, termination) goes through the injected
 * {@see ResponseEmitterInterface}. This keeps the whole class unit-testable —
 * including the JSON/redirect/exit paths that used to be untestable inline
 * `header`/`echo`/`exit`.
 *
 * @api
 */
final class InertiaAdapter
{
    /** @var array<string, mixed> */
    private array $sharedProps = [];

    public function __construct(
        private readonly HostComponentContextInterface $context,
        private readonly ResponseEmitterInterface $emitter = new PhpSapiEmitter(),
    ) {}

    public function share(string $key, mixed $value): void
    {
        $this->sharedProps[$key] = $value;
    }

    public function render(string $component, array $props = []): void
    {
        $server = $_SERVER;

        $page = [
            'component' => $component,
            'props' => $this->resolveProps($props),
            'url' => $this->currentUrl($server),
            'version' => $this->getVersion(),
        ];

        if (self::isInertiaRequest($server)) {
            $this->sendJson($page, $server);
        }

        $this->renderHtml($page);
    }

    public function location(string $url): never
    {
        if (self::isInertiaRequest($_SERVER)) {
            $this->emitter->header('X-Inertia-Location', $url);
            $this->emitter->status(409);
            $this->emitter->terminate();
        }

        $this->emitter->redirect($url);
        $this->emitter->terminate();
    }

    /**
     * Whether the current request is an Inertia XHR. Pure: takes the server
     * array (read once at the boundary) instead of touching `$_SERVER` directly.
     *
     * @param array<string, mixed> $server
     */
    public static function isInertiaRequest(array $server): bool
    {
        return isset($server['HTTP_X_INERTIA']) && $server['HTTP_X_INERTIA'] === 'true';
    }

    /**
     * Register + enqueue the SPA bundle (and optional stylesheet) for this host
     * component, cache-busted by the component's asset version.
     *
     * Host-neutral by design: the host plugin supplies the asset URLs — only it
     * knows its own {@see plugins_url()} — while the adapter contributes the
     * WordPress enqueue wiring and the version. This is deliberately independent
     * of {@see render()} (which emits only the mount node); call it from the
     * host's `admin_enqueue_scripts` / `wp_enqueue_scripts` hook.
     */
    public function enqueueAssets(
        string $handle,
        string $scriptSrc,
        ?string $styleSrc = null,
        array $scriptDeps = [],
    ): void {
        $version = $this->getVersion();

        AssetSupport::enqueueScript($handle, $scriptSrc, $scriptDeps, $version, true);

        if ($styleSrc !== null) {
            AssetSupport::enqueueStyle($handle, $styleSrc, [], $version);
        }
    }

    /**
     * The DOM id the SPA mounts on, namespaced by the host component so two
     * plugins never collide on `#middag-app`. The frontend entry must mount on
     * exactly this id.
     */
    public function mountId(): string
    {
        return $this->context->componentName() . '-app';
    }

    /**
     * The WordPress nonce action this component's admin SPA uses for CSRF,
     * derived from the component so each plugin owns an isolated nonce.
     */
    public function nonceAction(): string
    {
        return CsrfGuard::nonceAction($this->context->componentName());
    }

    private function resolveProps(array $props): array
    {
        $merged = array_merge($this->sharedProps, $props);

        // Auto-share the WP nonce as `csrfToken` so the SPA can echo it back via
        // the `X-WP-Nonce` header that CsrfGuard verifies on mutating requests.
        // An explicit share/prop of the same key wins (set before this point).
        if (!array_key_exists('csrfToken', $merged)) {
            $merged['csrfToken'] = SecuritySupport::createNonce($this->nonceAction());
        }

        $resolved = [];

        foreach ($merged as $key => $value) {
            $resolved[$key] = $value instanceof Closure ? $value() : $value;
        }

        // Props (including any `contract`) pass through verbatim. The adapter
        // accepts ONLY the canonical @middag-io/react PageContract and performs
        // no schema migration: a legacy contract is forwarded unchanged so the
        // frontend rejects it, rather than being silently rewritten here.
        return $resolved;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function isPartialReload(array $server, string $component): bool
    {
        return isset($server['HTTP_X_INERTIA_PARTIAL_COMPONENT'])
            && $server['HTTP_X_INERTIA_PARTIAL_COMPONENT'] === $component;
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return list<string>
     */
    private function partialData(array $server): array
    {
        $header = (string) ($server['HTTP_X_INERTIA_PARTIAL_DATA'] ?? '');

        return $header !== '' ? explode(',', $header) : [];
    }

    /**
     * @param array<string, mixed> $server
     */
    private function currentUrl(array $server): string
    {
        $uri = $server['REQUEST_URI'] ?? '/wp-admin/admin.php?page=' . $this->context->componentName();

        return (string) $uri;
    }

    private function getVersion(): string
    {
        return $this->context->assetVersion();
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $server
     */
    private function sendJson(array $page, array $server): never
    {
        // Handle partial reloads
        if ($this->isPartialReload($server, (string) $page['component'])) {
            $only = $this->partialData($server);
            if ($only !== []) {
                $page['props'] = array_intersect_key($page['props'], array_flip($only));
            }
        }

        $this->emitter->header('Content-Type', 'application/json');
        $this->emitter->header('X-Inertia', 'true');
        $this->emitter->header('Vary', 'X-Inertia');

        $this->emitter->write((string) wp_json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));

        $this->emitter->terminate();
    }

    /**
     * @param array<string, mixed> $page
     */
    private function renderHtml(array $page): void
    {
        $json = (string) wp_json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        // Escape exactly once at the output boundary, through the Escape seam.
        // The JSON above is the framework's internal page payload — it is NOT
        // pre-escaped, so this attr() call is the single (and only) HTML-attribute
        // escaping layer over the data-page value. Do not add another.
        $attr = EscapeSupport::attr($json);
        $mountId = EscapeSupport::attr($this->mountId());

        $this->emitter->write('<div id="' . $mountId . '" data-page="' . $attr . '"></div>');
    }
}
