<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Inertia;

use Middag\WordPress\Http\Inertia\PageContractNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Branch coverage for {@see PageContractNormalizer::normalize()} and its private
 * helpers (block / dense-table / column normalization). The target is pure array
 * transformation with no WordPress runtime dependency, so no stubs are exercised.
 *
 * @internal
 */
#[CoversClass(PageContractNormalizer::class)]
final class PageContractNormalizerTest extends TestCase
{
    // ─── Shell normalization ────────────────────────────────────────────────

    #[Test]
    public function adminShellIsRewrittenToProduct(): void
    {
        $result = PageContractNormalizer::normalize(['shell' => 'admin']);

        self::assertSame('product', $result['shell']);
    }

    #[Test]
    public function nonAdminShellIsLeftUntouched(): void
    {
        $result = PageContractNormalizer::normalize(['shell' => 'product']);

        self::assertSame('product', $result['shell']);
    }

    #[Test]
    public function missingShellIsNotAdded(): void
    {
        $result = PageContractNormalizer::normalize(['title' => 'x']);

        self::assertArrayNotHasKey('shell', $result);
    }

    // ─── Layout / region traversal ──────────────────────────────────────────

    #[Test]
    public function contractWithoutLayoutRegionsIsReturnedAsIs(): void
    {
        $contract = ['shell' => 'admin', 'layout' => ['title' => 'no regions']];

        $result = PageContractNormalizer::normalize($contract);

        self::assertSame('product', $result['shell']);
        self::assertSame(['title' => 'no regions'], $result['layout']);
    }

    #[Test]
    public function nonArrayRegionEntriesAreSkipped(): void
    {
        $contract = [
            'layout' => [
                'regions' => [
                    'sidebar' => 'not-an-array',
                    'footer' => 42,
                ],
            ],
        ];

        $result = PageContractNormalizer::normalize($contract);

        self::assertSame('not-an-array', $result['layout']['regions']['sidebar']);
        self::assertSame(42, $result['layout']['regions']['footer']);
    }

    // ─── Block normalization ────────────────────────────────────────────────

    #[Test]
    public function nonDenseTableBlockIsLeftUnchanged(): void
    {
        $block = ['type' => 'hero', 'data' => ['heading' => 'Hi']];

        self::assertSame($block, $this->firstBlock($this->normalizeBlock($block)));
    }

    #[Test]
    public function denseTableBlockWithoutDataIsLeftUnchanged(): void
    {
        $block = ['type' => 'dense_table'];

        self::assertSame($block, $this->firstBlock($this->normalizeBlock($block)));
    }

    #[Test]
    public function denseTableBlockWithNonArrayDataIsLeftUnchanged(): void
    {
        $block = ['type' => 'dense_table', 'data' => 'oops'];

        self::assertSame($block, $this->firstBlock($this->normalizeBlock($block)));
    }

    #[Test]
    public function blockWithoutTypeKeyIsLeftUnchanged(): void
    {
        $block = ['data' => ['columns' => []]];

        self::assertSame($block, $this->firstBlock($this->normalizeBlock($block)));
    }

    // ─── Column normalization ───────────────────────────────────────────────

    #[Test]
    public function badgeColumnWithVariantsBecomesStatusWithStatusMap(): void
    {
        $data = $this->denseTableData([
            'columns' => [
                ['key' => 'state', 'type' => 'badge', 'badgeVariants' => ['on' => 'success', 'off' => 'muted']],
            ],
        ]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame('status', $col['variant']);
        self::assertSame(['on' => 'success', 'off' => 'muted'], $col['statusMap']);
        self::assertArrayNotHasKey('type', $col);
        self::assertArrayNotHasKey('badgeVariants', $col);
    }

    #[Test]
    public function badgeColumnWithoutVariantsBecomesStatusWithoutStatusMap(): void
    {
        $data = $this->denseTableData(['columns' => [['type' => 'badge']]]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame('status', $col['variant']);
        self::assertArrayNotHasKey('statusMap', $col);
        self::assertArrayNotHasKey('type', $col);
    }

    #[Test]
    public function dateColumnBecomesTimestampVariant(): void
    {
        $data = $this->denseTableData(['columns' => [['type' => 'date']]]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame('timestamp', $col['variant']);
        self::assertArrayNotHasKey('type', $col);
    }

    #[Test]
    public function currencyColumnBecomesTextVariant(): void
    {
        $data = $this->denseTableData(['columns' => [['type' => 'currency']]]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame('text', $col['variant']);
        self::assertArrayNotHasKey('type', $col);
    }

    #[Test]
    public function unknownTypeWithoutVariantIsPromotedToVariant(): void
    {
        $data = $this->denseTableData(['columns' => [['type' => 'number']]]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame('number', $col['variant']);
        self::assertArrayNotHasKey('type', $col);
    }

    #[Test]
    public function unknownTypeWithExistingVariantIsLeftUntouched(): void
    {
        $data = $this->denseTableData(['columns' => [['type' => 'number', 'variant' => 'custom']]]);

        $col = $this->normalizeData($data)['columns'][0];

        // Neither branch fires: type is kept and the pre-set variant is preserved.
        self::assertSame('number', $col['type']);
        self::assertSame('custom', $col['variant']);
    }

    #[Test]
    public function columnWithoutTypeIsLeftUntouched(): void
    {
        $data = $this->denseTableData(['columns' => [['key' => 'label', 'header' => 'Label']]]);

        $col = $this->normalizeData($data)['columns'][0];

        self::assertSame(['key' => 'label', 'header' => 'Label'], $col);
    }

    #[Test]
    public function nonArrayColumnEntriesAreSkipped(): void
    {
        $data = $this->denseTableData(['columns' => ['scalar-col', ['type' => 'date']]]);

        $columns = $this->normalizeData($data)['columns'];

        self::assertSame('scalar-col', $columns[0]);
        self::assertSame('timestamp', $columns[1]['variant']);
    }

    #[Test]
    public function nonArrayColumnsContainerIsSkipped(): void
    {
        $data = $this->denseTableData(['columns' => 'not-an-array']);

        self::assertSame('not-an-array', $this->normalizeData($data)['columns']);
    }

    // ─── Pagination normalization ───────────────────────────────────────────

    #[Test]
    public function paginationCurrentPageAndPagesAreMigrated(): void
    {
        $data = $this->denseTableData(['pagination' => ['currentPage' => 3, 'pages' => 9, 'total' => 90]]);

        $pag = $this->normalizeData($data)['pagination'];

        self::assertSame(3, $pag['page']);
        self::assertSame(9, $pag['lastPage']);
        self::assertSame(90, $pag['total']);
        self::assertArrayNotHasKey('currentPage', $pag);
        self::assertArrayNotHasKey('pages', $pag);
    }

    #[Test]
    public function paginationAlreadyCanonicalIsPreserved(): void
    {
        $data = $this->denseTableData([
            'pagination' => ['currentPage' => 3, 'page' => 1, 'pages' => 9, 'lastPage' => 2],
        ]);

        $pag = $this->normalizeData($data)['pagination'];

        // Canonical keys already present -> legacy keys are NOT migrated over them.
        self::assertSame(1, $pag['page']);
        self::assertSame(2, $pag['lastPage']);
        self::assertSame(3, $pag['currentPage']);
        self::assertSame(9, $pag['pages']);
    }

    #[Test]
    public function nonArrayPaginationIsSkipped(): void
    {
        $data = $this->denseTableData(['pagination' => 'nope']);

        self::assertSame('nope', $this->normalizeData($data)['pagination']);
    }

    // ─── emptyMessage → emptyState ──────────────────────────────────────────

    #[Test]
    public function emptyMessageIsExpandedIntoEmptyState(): void
    {
        $data = $this->denseTableData(['emptyMessage' => 'Nothing found']);

        $normalized = $this->normalizeData($data);

        self::assertSame(
            ['title' => 'Nothing found', 'description' => 'Nothing found'],
            $normalized['emptyState'],
        );
        self::assertArrayNotHasKey('emptyMessage', $normalized);
    }

    #[Test]
    public function emptyMessageIsPreservedWhenEmptyStateAlreadyPresent(): void
    {
        $data = $this->denseTableData([
            'emptyMessage' => 'legacy',
            'emptyState' => ['title' => 'Kept', 'description' => 'Kept desc'],
        ]);

        $normalized = $this->normalizeData($data);

        self::assertSame(['title' => 'Kept', 'description' => 'Kept desc'], $normalized['emptyState']);
        self::assertSame('legacy', $normalized['emptyMessage']);
    }

    // ─── filters / sort defaults ────────────────────────────────────────────

    #[Test]
    public function filtersAndSortDefaultsAreAddedWhenMissing(): void
    {
        $normalized = $this->normalizeData($this->denseTableData([]));

        self::assertSame([], $normalized['filters']['available']);
        self::assertInstanceOf(stdClass::class, $normalized['filters']['applied']);
        self::assertEquals(new stdClass(), $normalized['filters']['applied']);
        self::assertSame(['column' => null, 'direction' => null], $normalized['sort']);
    }

    #[Test]
    public function existingFiltersAndSortAreNotOverwritten(): void
    {
        $data = $this->denseTableData([
            'filters' => ['available' => ['status'], 'applied' => ['status' => 'on']],
            'sort' => ['column' => 'name', 'direction' => 'asc'],
        ]);

        $normalized = $this->normalizeData($data);

        self::assertSame(['available' => ['status'], 'applied' => ['status' => 'on']], $normalized['filters']);
        self::assertSame(['column' => 'name', 'direction' => 'asc'], $normalized['sort']);
    }

    // ─── Integration: full contract through the public entry point ───────────

    #[Test]
    public function fullContractIsNormalizedEndToEnd(): void
    {
        $contract = [
            'shell' => 'admin',
            'layout' => [
                'regions' => [
                    'main' => [
                        [
                            'type' => 'dense_table',
                            'data' => [
                                'columns' => [
                                    ['type' => 'badge', 'badgeVariants' => ['a' => 'success']],
                                    ['type' => 'date'],
                                ],
                                'pagination' => ['currentPage' => 1, 'pages' => 4],
                                'emptyMessage' => 'Empty',
                            ],
                        ],
                    ],
                    'aside' => 'skipme',
                ],
            ],
        ];

        $result = PageContractNormalizer::normalize($contract);

        self::assertSame('product', $result['shell']);
        self::assertSame('skipme', $result['layout']['regions']['aside']);

        $data = $result['layout']['regions']['main'][0]['data'];
        self::assertSame('status', $data['columns'][0]['variant']);
        self::assertSame('timestamp', $data['columns'][1]['variant']);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(4, $data['pagination']['lastPage']);
        self::assertSame('Empty', $data['emptyState']['title']);
        self::assertArrayHasKey('filters', $data);
        self::assertArrayHasKey('sort', $data);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Wrap a single block in a minimal contract, normalize it, and return the
     * whole normalized contract (region traversal exercises normalizeBlock()).
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function normalizeBlock(array $block): array
    {
        return PageContractNormalizer::normalize([
            'layout' => ['regions' => ['main' => [$block]]],
        ]);
    }

    /**
     * Extract the first (only) block from a normalized single-block contract.
     *
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function firstBlock(array $normalized): array
    {
        return $normalized['layout']['regions']['main'][0];
    }

    /**
     * Build a dense_table block wrapping the given table data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function denseTableData(array $data): array
    {
        return ['type' => 'dense_table', 'data' => $data];
    }

    /**
     * Normalize a dense_table block and return its (normalized) `data` payload.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function normalizeData(array $block): array
    {
        return $this->firstBlock($this->normalizeBlock($block))['data'];
    }
}
