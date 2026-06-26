<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Privacy;

use Middag\WordPress\Privacy\Contract\PersonalDataProviderInterface;
use Middag\WordPress\Support\PrivacySupport;

/**
 * Wires host-supplied {@see PersonalDataProviderInterface} providers into the
 * WordPress personal-data export and erasure flows.
 *
 * The registrar is the only place that touches the WordPress privacy filters
 * (through {@see PrivacySupport}). It contributes one exporter entry and one
 * eraser entry per registered provider; when WordPress dispatches a request for
 * a given email + page it routes the call back to the owning provider. The
 * provider logic stays fully pluggable and OSS-clean — product packages
 * register against the contract without importing any proprietary code.
 *
 * @internal
 */
final class PrivacyRegistrar
{
    /**
     * @var array<string, PersonalDataProviderInterface> indexed by provider key
     */
    private array $providers = [];

    /**
     * Register a provider. Later registrations under the same key win, keeping
     * the WordPress exporter/eraser keyspace unique.
     */
    public function addProvider(PersonalDataProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * Hook the registered providers onto the WordPress privacy filters.
     *
     * Safe to call when no providers are registered (the filter callbacks then
     * return the running registry unchanged).
     */
    public function register(): void
    {
        PrivacySupport::registerExporters([$this, 'collectExporters']);
        PrivacySupport::registerErasers([$this, 'collectErasers']);
    }

    /**
     * Optionally suggest privacy-policy copy for the site's Privacy Policy
     * Guide. Kept separate from {@see register()} so callers opt in explicitly
     * (it is meant for the `admin_init` hook).
     */
    public function addPrivacyPolicyContent(string $pluginName, string $policyText): void
    {
        PrivacySupport::addPrivacyPolicyContent($pluginName, $policyText);
    }

    /**
     * The keys of all registered providers.
     *
     * @return array<int, string>
     */
    public function getRegisteredKeys(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Filter callback for `wp_privacy_personal_data_exporters`: append one
     * exporter entry per registered provider.
     *
     * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters
     *
     * @return array<string, array{exporter_friendly_name: string, callback: callable}>
     */
    public function collectExporters(array $exporters): array
    {
        foreach ($this->providers as $key => $provider) {
            $exporters[$key] = [
                'exporter_friendly_name' => $provider->label(),
                'callback' => fn (string $email, int $page = 1): array => $provider->export($email, $page),
            ];
        }

        return $exporters;
    }

    /**
     * Filter callback for `wp_privacy_personal_data_erasers`: append one eraser
     * entry per registered provider.
     *
     * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers
     *
     * @return array<string, array{eraser_friendly_name: string, callback: callable}>
     */
    public function collectErasers(array $erasers): array
    {
        foreach ($this->providers as $key => $provider) {
            $erasers[$key] = [
                'eraser_friendly_name' => $provider->label(),
                'callback' => fn (string $email, int $page = 1): array => $provider->erase($email, $page),
            ];
        }

        return $erasers;
    }
}
