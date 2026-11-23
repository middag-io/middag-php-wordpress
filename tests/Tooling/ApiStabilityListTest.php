<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Tooling;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every type in src/ carries exactly one of `@api` / `@internal`, and the
 * public surface listed in API-STABILITY.md is exactly the set of types
 * tagged `@api` in src/: a promotion, a demotion or a new `@api` type that the
 * file does not list fails here.
 *
 * @internal
 */
#[CoversNothing]
final class ApiStabilityListTest extends TestCase
{
    private const string DECLARATION = '~/\*\*((?:(?!\*/).)*?)\*/\s*(?:#\[[^\]]*\]\s*)*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)~s';

    #[Test]
    public function theListedPublicSurfaceIsExactlyTheApiTypesInSrc(): void
    {
        $tagged = $this->apiTypesInSrc();
        $listed = $this->listedTypes();

        self::assertNotEmpty($tagged, 'no @api type found: the scan is broken');
        self::assertSame([], array_values(array_diff($tagged, $listed)), '@api in src/ but missing from API-STABILITY.md');
        self::assertSame([], array_values(array_diff($listed, $tagged)), 'listed in API-STABILITY.md but not @api in src/');
        self::assertSame(count($listed), count(array_unique($listed)), 'a type is listed twice');
    }

    #[Test]
    public function everyTypeInSrcCarriesExactlyOneStabilityTag(): void
    {
        $violations = [];
        $inspected = 0;

        foreach ($this->declarationsInSrc() as $declaration) {
            ++$inspected;
            $tags = (int) (1 === preg_match('/@api\b/', $declaration['doc'])) + (int) (1 === preg_match('/@internal\b/', $declaration['doc']));
            if (1 !== $tags) {
                $violations[] = $declaration['type'] . (0 === $tags ? ' (no tag)' : ' (both tags)');
            }
        }

        self::assertGreaterThan(0, $inspected, 'no type found: the scan is broken');
        self::assertSame([], $violations, 'types in src/ must carry exactly one of @api / @internal');
    }

    /**
     * @return list<string>
     */
    private function apiTypesInSrc(): array
    {
        $types = [];
        foreach ($this->declarationsInSrc() as $declaration) {
            if (1 === preg_match('/@api\b/', $declaration['doc'])) {
                $types[] = $declaration['type'];
            }
        }

        sort($types);

        return array_values(array_unique($types));
    }

    /**
     * @return list<array{type: string, doc: string}>
     */
    private function declarationsInSrc(): array
    {
        $declarations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            if (1 !== preg_match('/^namespace ([^;]+);/m', $code, $namespace)) {
                continue;
            }

            preg_match_all(self::DECLARATION, $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $declarations[] = ['type' => $namespace[1] . '\\' . $match[2], 'doc' => $match[1]];
            }
        }

        return $declarations;
    }

    /**
     * @return list<string>
     */
    private function listedTypes(): array
    {
        $document = (string) file_get_contents(dirname(__DIR__, 2) . '/API-STABILITY.md');
        $start = strpos($document, "\n## The public surface\n");
        self::assertNotFalse($start, 'API-STABILITY.md has no "## The public surface" section');

        $end = strpos($document, "\n## ", $start + 1);
        $section = substr($document, $start, false === $end ? null : $end - $start);

        preg_match_all('/^- `(Middag\\\WordPress\\\[^`]+)`$/m', $section, $matches);

        $types = $matches[1];
        sort($types);

        return $types;
    }
}
