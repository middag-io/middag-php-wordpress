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

/**
 * Normalizes legacy PageContract arrays to the @middag-io/react v0.15+ schema.
 *
 * Converts the old PHP controller format to the canonical PageContract shape
 * expected by ContractPage on the frontend.
 *
 * Usage in controllers:
 *   $contract = PageContractNormalizer::normalize($legacyContract);
 *   InertiaAdapter::render('Page', ['contract' => $contract]);
 *
 * Schema migrations applied:
 *   - shell: "admin" -> "product"
 *   - column type: "badge" -> variant: "status" (with statusMap from badgeVariants)
 *   - column type: "date"/"currency" -> variant: "timestamp"/"text"
 *   - pagination: currentPage -> page, pages -> lastPage
 *   - emptyMessage -> emptyState { title, description }
 *   - searchable/searchPlaceholder preserved in table data
 *
 * @api
 */
final class PageContractNormalizer
{
    /**
     * Normalize a legacy contract array to canonical schema.
     *
     * @param array<string, mixed> $contract Legacy PageContract array
     *
     * @return array<string, mixed> Normalized PageContract array
     */
    public static function normalize(array $contract): array
    {
        // Shell normalization
        if (($contract['shell'] ?? '') === 'admin') {
            $contract['shell'] = 'product';
        }

        // Layout regions — normalize blocks
        if (isset($contract['layout']['regions'])) {
            foreach ($contract['layout']['regions'] as &$blocks) {
                if (!is_array($blocks)) {
                    continue;
                }
                foreach ($blocks as &$block) {
                    $block = self::normalizeBlock($block);
                }
            }
            unset($blocks, $block);
        }

        return $contract;
    }

    /**
     * Normalize a single block descriptor.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private static function normalizeBlock(array $block): array
    {
        if (($block['type'] ?? '') === 'dense_table' && isset($block['data']) && is_array($block['data'])) {
            $block['data'] = self::normalizeDenseTable($block['data']);
        }

        return $block;
    }

    /**
     * Normalize dense_table block data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function normalizeDenseTable(array $data): array
    {
        // Columns: type+badgeVariants -> variant+statusMap
        if (isset($data['columns']) && is_array($data['columns'])) {
            foreach ($data['columns'] as &$col) {
                if (is_array($col)) {
                    $col = self::normalizeColumn($col);
                }
            }
            unset($col);
        }

        // Pagination: currentPage->page, pages->lastPage
        if (isset($data['pagination']) && is_array($data['pagination'])) {
            $pag = &$data['pagination'];
            if (isset($pag['currentPage']) && !isset($pag['page'])) {
                $pag['page'] = $pag['currentPage'];
                unset($pag['currentPage']);
            }
            if (isset($pag['pages']) && !isset($pag['lastPage'])) {
                $pag['lastPage'] = $pag['pages'];
                unset($pag['pages']);
            }
            unset($pag);
        }

        // emptyMessage -> emptyState
        if (isset($data['emptyMessage']) && !isset($data['emptyState'])) {
            $data['emptyState'] = [
                'title' => $data['emptyMessage'],
                'description' => $data['emptyMessage'],
            ];
            unset($data['emptyMessage']);
        }

        // Ensure filters structure
        if (!isset($data['filters'])) {
            $data['filters'] = ['available' => [], 'applied' => (object) []];
        }

        // Ensure sort structure
        if (!isset($data['sort'])) {
            $data['sort'] = ['column' => null, 'direction' => null];
        }

        return $data;
    }

    /**
     * Normalize a column definition.
     *
     * type: "badge" + badgeVariants -> variant: "status" + statusMap
     * type: "date" -> variant: "timestamp"
     * type: "currency" -> variant: "text" (formatted server-side)
     *
     * @param array<string, mixed> $col
     *
     * @return array<string, mixed>
     */
    private static function normalizeColumn(array $col): array
    {
        $type = $col['type'] ?? null;

        if ($type === 'badge') {
            $col['variant'] = 'status';
            if (isset($col['badgeVariants'])) {
                $col['statusMap'] = $col['badgeVariants'];
                unset($col['badgeVariants']);
            }
            unset($col['type']);
        } elseif ($type === 'date') {
            $col['variant'] = 'timestamp';
            unset($col['type']);
        } elseif ($type === 'currency') {
            $col['variant'] = 'text';
            unset($col['type']);
        } elseif ($type !== null && !isset($col['variant'])) {
            $col['variant'] = $type;
            unset($col['type']);
        }

        return $col;
    }
}
