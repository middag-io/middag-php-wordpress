<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Privacy\Contract;

/**
 * Contract a host/product package implements to plug domain data into the
 * WordPress personal-data export and erasure flows.
 *
 * The adapter knows nothing about the domain: it registers the WordPress
 * filters and, when WordPress dispatches a request for a given email address,
 * delegates to the registered providers. Product packages supply the actual
 * personal data WITHOUT importing any proprietary namespace — they depend only
 * on this OSS contract.
 *
 * Implementations are paginated: WordPress invokes the exporter/eraser
 * repeatedly with an increasing 1-based page number until the returned
 * `done` flag is `true`.
 *
 * @api
 */
interface PersonalDataProviderInterface
{
    /**
     * Stable, unique identifier used as the WordPress exporter/eraser key
     * (e.g. `my-plugin-orders`). Must be slug-like and constant across runs.
     */
    public function key(): string;

    /**
     * Human-readable label shown in the WordPress privacy tooling
     * (the exporter/eraser "friendly name").
     */
    public function label(): string;

    /**
     * Export the personal data this provider holds for the given email.
     *
     * Return the WordPress exporter result shape:
     * `['data' => array<int, array{group_id: string, group_label: string, item_id: string, data: array<int, array{name: string, value: mixed}>}>, 'done' => bool]`.
     *
     * @param string $email the email address being exported
     * @param int    $page  1-based pagination cursor
     *
     * @return array{data: array<int, array<string, mixed>>, done: bool}
     */
    public function export(string $email, int $page): array;

    /**
     * Erase (or anonymise) the personal data this provider holds for the email.
     *
     * Return the WordPress eraser result shape:
     * `['items_removed' => bool, 'items_retained' => bool, 'messages' => array<int, string>, 'done' => bool]`.
     *
     * @param string $email the email address being erased
     * @param int    $page  1-based pagination cursor
     *
     * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public function erase(string $email, int $page): array;
}
