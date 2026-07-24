<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Http\Controller;

use Middag\Framework\Http\Controller\AbstractController;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use RuntimeException;

/**
 * Page-controller base for the WordPress adapter — the Inertia counterpart to
 * the REST-only {@see AbstractWpRestController}.
 *
 * Extends the framework {@see AbstractController} (so it inherits the full
 * ControllerInterface surface plus the redirect/flash/response/getService
 * helpers) and adds the two things the framework base leaves to the host:
 *
 * - `render()` — delegates to the per-request {@see InertiaAdapter} resolved
 *   from the container, so a controller calls `$this->render('Component', $props)`
 *   instead of wiring Inertia by hand.
 * - the auth gate — `setRequireLogin()` / `setRequireCapabilities()` mapped onto
 *   WordPress's own functions.
 *
 * It deliberately carries NO Moodle-only concerns (sesskey, context objects,
 * page layout/heading/navbar). REST stays on its own base: the WP REST
 * lifecycle (register_rest_route + permission_callback over WP_REST_Request)
 * does not match the kernel request → handle() cycle, so the two bases do not
 * share an inheritance chain (see the adapter's first ADR).
 *
 * @api
 */
abstract class AbstractWpController extends AbstractController
{
    /**
     * Require an authenticated WordPress user; unauthenticated visitors are sent
     * to the login screen (WordPress's `auth_redirect()` halts and redirects).
     */
    public function setRequireLogin(): void
    {
        if (!\is_user_logged_in()) {
            \auth_redirect();
        }
    }

    /**
     * Require every listed capability of the current user, or halt with a 403.
     *
     * WordPress has no Moodle-style context/instance model. The mapping:
     * - `$instanceId > 0` → `current_user_can($cap, $instanceId)`, i.e. the id is
     *   passed as the meta-capability object id WordPress resolves via
     *   `map_meta_cap` (e.g. `current_user_can('edit_post', 123)`).
     * - `$instanceId === 0` → `current_user_can($cap)`, a global capability check.
     * - `$context` (string) has no native WordPress equivalent and is advisory
     *   only; it is accepted for cross-adapter signature parity and ignored here.
     *
     * @param array<int, string> $capabilities
     */
    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void
    {
        foreach ($capabilities as $capability) {
            $allowed = $instanceId > 0
                ? \current_user_can($capability, $instanceId)
                : \current_user_can($capability);

            if (!$allowed) {
                \wp_die(
                    'You do not have permission to access this page.',
                    '',
                    ['response' => 403],
                );
            }
        }
    }

    /**
     * Render an Inertia component through the container-bound adapter.
     *
     * @param array<string, mixed> $props
     */
    protected function render(string $component, array $props = []): void
    {
        $this->inertia()->render($component, $props);
    }

    /**
     * Resolve the per-request Inertia adapter from the container.
     */
    private function inertia(): InertiaAdapter
    {
        $adapter = $this->getService(InertiaAdapter::class);

        if (!$adapter instanceof InertiaAdapter) {
            throw new RuntimeException('No InertiaAdapter is bound in the container; cannot render a page.');
        }

        return $adapter;
    }
}
