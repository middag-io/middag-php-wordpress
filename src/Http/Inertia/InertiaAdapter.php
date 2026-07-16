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

use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaResponse;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\PhpSapiEmitter;
use Middag\WordPress\Http\Security\CsrfGuard;
use Middag\WordPress\Support\AssetSupport;
use Middag\WordPress\Support\EscapeSupport;
use Middag\WordPress\Support\SecuritySupport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-component Inertia adapter for the WordPress admin SPA pipeline.
 *
 * Thin WordPress plumbing over the framework's Inertia v3 wire: the whole
 * protocol — closure/`optional()`/`defer()`/`merge()` prop resolution, partial
 * reloads (`only`/`except`), deferred/merge/match metadata, asset-version skew
 * (409 + `X-Inertia-Location`), `X-Inertia-Reset` and entity normalization — is
 * delegated to {@see InertiaResponse} through
 * {@see InertiaFactory::render()}. This adapter contributes only the parts that
 * are genuinely WordPress: the CSRF nonce shared as `csrfToken`, the
 * component-namespaced mount node (`{component}-app`), the wp-admin HTML shell,
 * the per-component asset version, the SPA asset enqueue, and the XHR detection
 * helper the admin router intercepts on.
 *
 * Instance-scoped: each host plugin builds one adapter from its own
 * {@see HostComponentContextInterface} (resolved through the plugin's own DI
 * container), so two plugins living in the same request never share shared-props,
 * mount id, CSRF nonce action, or asset version. The framework wire it delegates
 * to is configured (version + HTML bootstrap) per render(), which is safe because
 * wp-admin serves exactly one component per request — the framework statics are
 * the documented adapter boot seam.
 *
 * Request/response boundaries follow the CsrfGuard split: the framework wire
 * reads the request superglobals at its own boundary, while every response
 * side-effect (status, headers, body, redirect, termination) goes through the
 * injected {@see ResponseEmitterInterface}. This keeps the JSON/redirect/exit
 * paths that used to be untestable inline `header`/`echo`/`exit` assertable.
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

    /**
     * Render an Inertia page for this component, emitting the response through the
     * injected emitter.
     *
     * Builds the props (shared + local + auto `csrfToken`) and hands them to the
     * framework wire, which decides between the JSON (Inertia XHR) and HTML shell
     * (first visit) branches and applies every v3 rule. The Inertia XHR branch
     * pre-empts the wp-admin HTML shell and therefore terminates; a full visit
     * writes the mount node and returns so WordPress finishes the admin page.
     *
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = []): void
    {
        $this->configureFrameworkWire();

        $response = InertiaFactory::render($component, $this->resolveProps($props))->toResponse();

        $this->emit($response);

        if (self::isInertiaRequest($_SERVER)) {
            $this->emitter->terminate();
        }
    }

    public function location(string $url): never
    {
        $server = $_SERVER;

        if (self::isInertiaRequest($server)) {
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

    /**
     * Point the framework's Inertia wire at THIS component before delegating:
     * its own asset version (each plugin cache-busts independently) and a
     * wp-admin HTML bootstrap that mounts the SPA on the component-namespaced
     * node with the page payload in `data-page`.
     *
     * Set per render because wp-admin serves one component per request, so there
     * is never a second component's render in flight to clobber; the framework's
     * static version manager and HTML bootstrap are the documented adapter boot
     * seam for exactly this.
     */
    private function configureFrameworkWire(): void
    {
        InertiaVersionManager::setVersion($this->getVersion());

        InertiaFactory::setHtmlBootstrap(
            fn (array $page, string $json, string $attr): Response => new Response($this->htmlShell($json)),
        );
    }

    /**
     * Build the props handed to the framework wire: shared props overlaid by the
     * per-render local props, plus the auto-shared WP nonce.
     *
     * The nonce is exposed as `csrfToken` so the SPA can echo it back via the
     * `X-WP-Nonce` header that CsrfGuard verifies on mutating requests; an
     * explicit share/prop of the same key wins. Everything else (closures,
     * `optional()`/`defer()`/`merge()` wrappers, and any `contract` envelope) is
     * forwarded verbatim — resolution, partial-reload filtering and normalization
     * are the framework wire's responsibility, and a legacy contract is passed
     * through unchanged so the frontend rejects it rather than being silently
     * rewritten here.
     *
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resolveProps(array $props): array
    {
        $merged = array_merge($this->sharedProps, $props);

        if (!array_key_exists('csrfToken', $merged)) {
            $merged['csrfToken'] = SecuritySupport::createNonce($this->nonceAction());
        }

        return $merged;
    }

    /**
     * The wp-admin mount node the framework's HTML branch writes on a first
     * visit: the component-namespaced div carrying the page payload in
     * `data-page`.
     *
     * Escapes exactly once at this output boundary, through the Escape seam. The
     * JSON is the framework's page payload — it is NOT pre-escaped, so this
     * attr() call is the single (and only) HTML-attribute escaping layer over the
     * data-page value. Do not add another.
     */
    private function htmlShell(string $json): string
    {
        return '<div id="' . EscapeSupport::attr($this->mountId()) . '" data-page="' . EscapeSupport::attr($json) . '"></div>';
    }

    /**
     * Emit a framework HttpFoundation response through the WordPress emitter seam
     * (status, headers preserving their original case, body). Headers keep their
     * case so an Inertia client reads `X-Inertia`, `Vary`, etc. exactly.
     */
    private function emit(Response $response): void
    {
        $this->emitter->status($response->getStatusCode());

        foreach ($response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $this->emitter->header($name, (string) $value);
            }
        }

        $body = $response->getContent();

        if ($body !== false && $body !== '') {
            $this->emitter->write($body);
        }
    }

    private function getVersion(): string
    {
        return $this->context->assetVersion();
    }
}
