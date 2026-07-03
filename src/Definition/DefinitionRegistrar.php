<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Definition;

use InvalidArgumentException;
use Middag\WordPress\Support\HookSupport;
use Middag\WordPress\Support\PostTypeSupport;
use Middag\WordPress\Support\ShortcodeSupport;

/**
 * Registers declarative host definitions (post types, taxonomies, cron
 * intervals) with WordPress. The WP counterpart of the moodle adapter's
 * `Definition/` concern: describe the plugin's registrations as data, wire
 * them in one call from the `init` hook.
 *
 * @api
 */
final class DefinitionRegistrar
{
    /**
     * @param list<CronScheduleDefinition|PostTypeDefinition|ShortcodeDefinition|TaxonomyDefinition> $definitions
     */
    public function register(array $definitions): void
    {
        $schedules = [];

        foreach ($definitions as $definition) {
            match (true) {
                $definition instanceof PostTypeDefinition => PostTypeSupport::registerPostType($definition->slug, $definition->toArgs()),
                $definition instanceof TaxonomyDefinition => PostTypeSupport::registerTaxonomy($definition->slug, $definition->objectTypes, $definition->toArgs()),
                $definition instanceof ShortcodeDefinition => $this->registerShortcode($definition),
                $definition instanceof CronScheduleDefinition => $schedules[] = $definition,
                default => throw new InvalidArgumentException('Unsupported definition: ' . get_debug_type($definition)),
            };
        }

        if ($schedules !== []) {
            $this->registerSchedules($schedules);
        }
    }

    private function registerShortcode(ShortcodeDefinition $definition): void
    {
        $render = $definition->render;
        if (!is_callable($render)) {
            throw new InvalidArgumentException(sprintf('Shortcode "%s" render must be callable.', $definition->tag));
        }

        ShortcodeSupport::add($definition->tag, $render);
    }

    /**
     * @param non-empty-list<CronScheduleDefinition> $schedules
     */
    private function registerSchedules(array $schedules): void
    {
        HookSupport::addFilter('cron_schedules', static function (array $registered) use ($schedules): array {
            foreach ($schedules as $schedule) {
                $registered[$schedule->slug] = [
                    'interval' => $schedule->intervalSeconds,
                    'display' => $schedule->display,
                ];
            }

            return $registered;
        });
    }
}
