<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Kernel\Loader;

use Middag\Framework\Kernel\Loader\HookfileLoader;
use Middag\WordPress\Support\HookSupport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * WordPress-specific hookfile discovery.
 *
 * Hookfiles are PHP scripts loaded at boot to register signal subscriptions,
 * extend services, or otherwise inject behavior into the MIDDAG runtime
 * without packaging a full extension. Three discovery sources, in load order:
 *
 *   1. `WP_CONTENT_DIR/middag_hooks.php`                — site-local override
 *   2. `WP_CONTENT_DIR/themes/{active}/middag_hooks.php` — active theme
 *   3. Plugins/themes responding to the `middag_hookfiles` filter and
 *      returning a list of absolute paths.
 *
 * Source order matters: later sources override earlier ones because the bus
 * reflects last-wins semantics for re-registered keys. Plugin contributions
 * are last so they can patch site/theme.
 *
 * @api
 */
final class WordPressHookfileLoader extends HookfileLoader
{
    private const HOOKFILE_BASENAME = 'middag_hooks.php';

    /**
     * @param string      $filterName  WordPress filter consulted for plugin/theme
     *                                 contributions. Filter callbacks receive an
     *                                 array and return an array of absolute paths.
     * @param null|string $contentDir  Override WP_CONTENT_DIR. Defaults to the
     *                                 constant when not provided. Tests inject this
     *                                 so they don't depend on a one-shot constant.
     * @param null|string $activeTheme Override get_stylesheet() result. Defaults
     *                                 to the function's return value when null.
     */
    public function __construct(
        private readonly string $filterName = 'middag_hookfiles',
        private readonly ?string $contentDir = null,
        private readonly ?string $activeTheme = null,
        LoggerInterface $logger = new NullLogger(),
    ) {
        parent::__construct($logger);
    }

    /**
     * @return string[] absolute, readable hookfile paths
     */
    protected function discoverPaths(): array
    {
        $paths = [];

        $content_dir = $this->contentDir();
        if ($content_dir !== null) {
            $this->maybeAdd($paths, $content_dir . '/' . self::HOOKFILE_BASENAME);

            $active_theme = $this->activeThemeSlug();
            if ($active_theme !== null) {
                $this->maybeAdd(
                    $paths,
                    $content_dir . '/themes/' . $active_theme . '/' . self::HOOKFILE_BASENAME,
                );
            }
        }

        foreach ($this->filterContributions() as $contributed) {
            $this->maybeAdd($paths, $contributed);
        }

        return array_values(array_unique($paths));
    }

    private function contentDir(): ?string
    {
        if ($this->contentDir !== null) {
            return $this->contentDir !== '' ? $this->contentDir : null;
        }

        if (defined('WP_CONTENT_DIR')) {
            $value = constant('WP_CONTENT_DIR');

            return is_string($value) && $value !== '' ? $value : null;
        }

        return null;
    }

    private function activeThemeSlug(): ?string
    {
        if ($this->activeTheme !== null) {
            return $this->activeTheme !== '' ? $this->activeTheme : null;
        }

        if (function_exists('get_stylesheet')) {
            $slug = get_stylesheet();

            return is_string($slug) && $slug !== '' ? $slug : null;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function filterContributions(): array
    {
        $result = HookSupport::applyFilters($this->filterName, []);

        if (!is_array($result)) {
            return [];
        }

        $contributions = [];
        foreach ($result as $candidate) {
            if (is_string($candidate)) {
                $contributions[] = $candidate;
            }
        }

        return $contributions;
    }

    /**
     * @param string[] $paths
     */
    private function maybeAdd(array &$paths, string $candidate): void
    {
        if ($candidate === '' || !is_file($candidate) || !is_readable($candidate)) {
            return;
        }

        $paths[] = $candidate;
    }
}
