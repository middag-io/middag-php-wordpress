<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture guard (WP-002): no default in `src/` names a product. A
 * consumer that configures nothing must not inherit a vendor's hook names,
 * text domain, option prefixes or cache groups, and every product (MIDDAG's
 * own included) passes its own values in its composition root.
 *
 * The scan covers every string literal and every comment and docblock of
 * `src/`, so an example in a docblock cannot teach a product name either. It
 * is case-sensitive and looks for the lowercase product name. Allowed forms:
 *
 * - `middaglib`, the neutral library marker ("came from this library, not
 *   configured"), used by every neutral default;
 * - `middag-io`, `middag.io` and `middag.dev`, the package, the copyright
 *   header and the documentation site.
 *
 * The PHP namespace (`Middag\`) and the uppercase product name are not
 * matched. The guard fails closed when `src/` holds no PHP file.
 *
 * @internal
 */
#[CoversNothing]
final class NoProductNamedDefaultTest extends TestCase
{
    private const string PRODUCT_NAME = '/middag(?!lib|-io|\.io|\.dev)/';

    /** @var list<int> */
    private const array SCANNED_TOKENS = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    #[Test]
    #[DataProvider('sourceFileProvider')]
    public function sourceFileNamesNoProduct(string $path): void
    {
        $violations = [];

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (!is_array($token)) {
                continue;
            }

            if (!in_array($token[0], self::SCANNED_TOKENS, true)) {
                continue;
            }

            if (preg_match(self::PRODUCT_NAME, $token[1]) === 1) {
                $violations[] = sprintf('line %d: %s', $token[2], trim($token[1]));
            }
        }

        self::assertSame([], $violations, sprintf(
            '%s names a product in a default, string or comment; use the neutral marker middaglib or a neutral example (acme):%s%s',
            basename($path),
            PHP_EOL,
            implode(PHP_EOL, $violations),
        ));
    }

    /**
     * Every PHP source file under src/.
     *
     * @return array<string, array{string}>
     */
    public static function sourceFileProvider(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = [$file->getPathname()];
            }
        }

        self::assertNotSame([], $files, 'src/ holds no PHP file; the guard has nothing to scan.');

        ksort($files);

        return $files;
    }

    #[Test]
    public function theGuardDetectsAProductNameAndAllowsTheNeutralForms(): void
    {
        self::assertMatchesRegularExpression(self::PRODUCT_NAME, "'middag/'");
        self::assertMatchesRegularExpression(self::PRODUCT_NAME, "'middag_hooks.php'");
        self::assertMatchesRegularExpression(self::PRODUCT_NAME, '// loads middag_hookfiles');
        self::assertDoesNotMatchRegularExpression(self::PRODUCT_NAME, "'middaglib_hookfiles'");
        self::assertDoesNotMatchRegularExpression(self::PRODUCT_NAME, ' * @author      Michael Meneses <michael@middag.io>');
        self::assertDoesNotMatchRegularExpression(self::PRODUCT_NAME, ' * middag-io/wordpress, see https://docs.middag.dev');
    }
}
